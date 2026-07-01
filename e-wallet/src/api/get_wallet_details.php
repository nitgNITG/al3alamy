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

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['wallet_uuid'])) {
    respond('error', 'wallet_uuid is required');
}

$wallet_uuid = $data['wallet_uuid'];

// الحصول على تفاصيل المحفظة
$query = "SELECT * FROM wallets WHERE uuid = ? AND platform_uuid = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$wallet_uuid, $platform_uuid]);

if ($stmt->rowCount() === 0) {
    respond('error', 'Wallet not found or does not belong to this platform');
}

$wallet = $stmt->fetch(PDO::FETCH_ASSOC);

// الحصول على عدد المعاملات وتقسيمها حسب النوع
$query = "SELECT 
            COUNT(*) AS total_transactions,
            SUM(CASE WHEN type = 'recharge' THEN 1 ELSE 0 END) AS recharge_transactions,
            SUM(CASE WHEN type = 'payment' THEN 1 ELSE 0 END) AS payment_transactions,
            SUM(CASE WHEN type = 'transfer' THEN 1 ELSE 0 END) AS transfer_transactions
          FROM transactions
          WHERE wallet_uuid = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$wallet_uuid]);

$transaction_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// دمج نتائج المحفظة مع إحصائيات المعاملات
$wallet_details = array_merge($wallet, $transaction_stats);

// إرسال الرد
respond('success', 'Wallet details retrieved', $wallet_details);
?>
