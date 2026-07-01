<?php
require '../config/db.php';
require '../config/auth.php';
require '../utils/helpers.php';

// الحصول على الهيدر الخاص بالتوكن
$headers = apache_request_headers();
if (!isset($headers['Authorization']) || !preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
    respond('error', 'Missing or invalid token');
}

$api_key = $matches[1];

// التحقق من صلاحية التوكن
$platform_uuid = validate_token($api_key);
if (!$platform_uuid) {
    respond('error', 'Invalid API Key');
}

// الحصول على البيانات المرسلة في الطلب
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['platform_uuid'])) {
    respond('error', 'platform_uuid is required');
}

$requested_platform_uuid = $data['platform_uuid'];

// استرجاع معلومات المنصة من قاعدة البيانات
$query = "SELECT id, uuid, name, api_key, created_at FROM platforms WHERE uuid = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$requested_platform_uuid]);

if ($stmt->rowCount() === 0) {
    respond('error', 'Platform not found');
}

$platform = $stmt->fetch(PDO::FETCH_ASSOC);

// استرجاع جميع المحافظ الخاصة بالمنصة
$query = "SELECT uuid, balance FROM wallets WHERE platform_uuid = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$requested_platform_uuid]);

$wallets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// استرجاع عدد المحافظ
$num_wallets = count($wallets);

// استرجاع عدد المعاملات وتقسيمها حسب النوع
$transactions_summary = [
    'payment' => 0,
    'recharge' => 0,
    'transfer' => 0
];

foreach ($wallets as &$wallet) {
    $wallet_uuid = $wallet['uuid'];
    $query = "SELECT type FROM transactions WHERE wallet_uuid = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$wallet_uuid]);
    
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تحديث عدد المعاملات حسب النوع
    foreach ($transactions as $transaction) {
        $transactions_summary[$transaction['type']]++;
    }
    
    $wallet['transactions'] = $transactions;
}

// تجهيز الرد النهائي
$response = [
    'platform' => $platform,
    'num_wallets' => $num_wallets,
    'transactions_summary' => $transactions_summary,
    'wallets' => $wallets
];

respond('success', 'Platform and related data retrieved', $response);
?>
