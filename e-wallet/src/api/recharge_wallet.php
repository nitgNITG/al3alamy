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
$platform_id = validate_token($api_key);
if (!$platform_id) {
    respond('error', 'Invalid API Key');
}

// قراءة البيانات من الطلب
$data = json_decode(file_get_contents('php://input'), true);
$wallet_uuid = $data['wallet_uuid'];
$amount = $data['amount'];
$description = isset($data['description']) ? $data['description'] : '';

// التحقق من صحة البيانات
if (empty($wallet_uuid) || empty($amount) || !is_numeric($amount)) {
    respond('error', 'Invalid input data');
}

// توليد UUID جديد للمعاملة
$transaction_uuid = generate_uuid();

// تحديث رصيد المحفظة وإدخال العملية في قاعدة البيانات
try {
    // بدء المعاملة
    $pdo->beginTransaction();

    // التحقق من وجود المحفظة
    $wallet_query = "SELECT balance FROM wallets WHERE uuid = ?";
    $wallet_stmt = $pdo->prepare($wallet_query);
    $wallet_stmt->execute([$wallet_uuid]);
    $wallet = $wallet_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$wallet) {
        throw new Exception('Wallet not found');
    }

    // تحديث رصيد المحفظة
    $new_balance = $wallet['balance'] + $amount;
    $update_wallet_query = "UPDATE wallets SET balance = ? WHERE uuid = ?";
    $update_wallet_stmt = $pdo->prepare($update_wallet_query);
    $update_wallet_stmt->execute([$new_balance, $wallet_uuid]);

    // إدخال العملية في جدول المعاملات
    $transaction_query = "INSERT INTO transactions (uuid, wallet_uuid, type, amount, description) VALUES (?, ?, 'recharge', ?, ?)";
    $transaction_stmt = $pdo->prepare($transaction_query);
    $transaction_stmt->execute([$transaction_uuid, $wallet_uuid, $amount, $description]);

    // إنهاء المعاملة
    $pdo->commit();

    respond('success', 'Wallet recharged', ['transaction_uuid' => $transaction_uuid]);
} catch (Exception $e) {
    // التراجع عن المعاملة في حالة وجود خطأ
    $pdo->rollBack();
    respond('error', 'Database error: ' . $e->getMessage());
}
?>