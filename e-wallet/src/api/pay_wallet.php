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
$wallet_uuid = $data['wallet_uuid'] ?? null;
$amount = $data['amount'] ?? null;

// التحقق من صحة المدخلات
if (!$wallet_uuid || !is_numeric($amount) || $amount <= 0) {
    respond('error', 'Invalid input');
}

// التحقق من وجود المحفظة ورصيدها
$query = "SELECT id, balance FROM wallets WHERE uuid = ? AND platform_uuid = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$wallet_uuid, $platform_uuid]);

if ($stmt->rowCount() === 0) {
    respond('error', 'Wallet not found or does not belong to this platform');
}

$wallet = $stmt->fetch(PDO::FETCH_ASSOC);

// التحقق من أن الرصيد كافٍ
if ($wallet['balance'] < $amount) {
    respond('error', 'Insufficient funds');
}

// خصم المبلغ من المحفظة
$query = "UPDATE wallets SET balance = balance - ? WHERE uuid = ?";
$stmt = $pdo->prepare($query);
$updated = $stmt->execute([$amount, $wallet_uuid]);

// التحقق من نجاح عملية الخصم
if ($updated) {
    // تسجيل العملية في سجل المعاملات
    $transaction_uuid = generate_uuid();
    $query = "INSERT INTO transactions (uuid, wallet_uuid, amount, type, description) VALUES (?, ?, ?, 'payment', ?)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$transaction_uuid, $wallet_uuid, $amount, $data['description'] ?? '']);

    respond('success', 'Payment made', ['transaction_uuid' => $transaction_uuid]);
} else {
    respond('error', 'Failed to process payment');
}
?>
