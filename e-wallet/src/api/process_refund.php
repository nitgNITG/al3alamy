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

// الحصول على البيانات من الطلب
$data = json_decode(file_get_contents('php://input'), true);
$transaction_uuid = $data['transaction_uuid'] ?? null;
$amount = $data['amount'] ?? null;
$reason = $data['reason'] ?? null;

// التحقق من صحة المدخلات
if (!$transaction_uuid || !$amount || !$reason) {
    respond('error', 'Transaction UUID, amount, and reason are required');
}

// التحقق من وجود المعاملة
$query = "SELECT * FROM transactions WHERE uuid = ? AND status = 'completed'";
$stmt = $pdo->prepare($query);
$stmt->execute([$transaction_uuid]);

if ($stmt->rowCount() === 0) {
    respond('error', 'Transaction not found or not eligible for refund');
}

$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

// التحقق من مطابقة المبلغ
if ($transaction['amount'] !== $amount) {
    respond('error', 'Amount does not match the transaction amount');
}

// تحديث حالة المعاملة إلى "استرجاع"
$query = "UPDATE transactions SET is_refund = 1, description = ?, status = 'completed' WHERE uuid = ?";
$stmt = $pdo->prepare($query);
$updated = $stmt->execute([$reason, $transaction_uuid]);

if ($updated) {
    // إضافة المبلغ إلى المحفظة
    $query = "UPDATE wallets SET balance = balance + ? WHERE uuid = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$amount, $transaction['wallet_uuid']]);

    respond('success', 'Refund processed successfully');
} else {
    respond('error', 'Failed to process refund');
}
?>
