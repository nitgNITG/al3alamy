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
$source_wallet_uuid = $data['source_wallet_uuid'] ?? null;
$destination_wallet_uuid = $data['destination_wallet_uuid'] ?? null;
$amount = $data['amount'] ?? null;
$description = $data['description'] ?? '';

// التحقق من صحة المدخلات
if (!$source_wallet_uuid || !$destination_wallet_uuid || !is_numeric($amount) || $amount <= 0) {
    respond('error', 'Invalid input');
}

// التحقق من وجود المحفظتين ورصيد المحفظة المصدر
$query = "SELECT uuid, balance FROM wallets WHERE uuid IN (?, ?) AND platform_uuid = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$source_wallet_uuid, $destination_wallet_uuid, $platform_uuid]);

if ($stmt->rowCount() < 2) {
    respond('error', 'One or both wallets not found or do not belong to this platform');
}

$wallets = $stmt->fetchAll(PDO::FETCH_ASSOC);
$source_wallet = array_filter($wallets, function($wallet) use ($source_wallet_uuid) {
    return $wallet['uuid'] === $source_wallet_uuid;
});
$destination_wallet = array_filter($wallets, function($wallet) use ($destination_wallet_uuid) {
    return $wallet['uuid'] === $destination_wallet_uuid;
});

$source_wallet = reset($source_wallet);
$destination_wallet = reset($destination_wallet);

// التحقق من أن الرصيد كافٍ
if ($source_wallet['balance'] < $amount) {
    respond('error', 'Insufficient funds');
}

// خصم المبلغ من المحفظة المصدر
$query = "UPDATE wallets SET balance = balance - ? WHERE uuid = ?";
$stmt = $pdo->prepare($query);
$updated = $stmt->execute([$amount, $source_wallet_uuid]);

if ($updated) {
    // إضافة المبلغ إلى المحفظة الوجهة
    $query = "UPDATE wallets SET balance = balance + ? WHERE uuid = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$amount, $destination_wallet_uuid]);

    // تسجيل العملية في سجل المعاملات
    $transaction_uuid = generate_uuid();
    $query = "INSERT INTO transactions (uuid, wallet_uuid, type, amount, description) VALUES (?, ?, 'transfer', ?, ?)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$transaction_uuid, $source_wallet_uuid, $amount, $description]);

    respond('success', 'Funds transferred', ['transaction_uuid' => $transaction_uuid]);
} else {
    respond('error', 'Failed to process transfer');
}
?>
