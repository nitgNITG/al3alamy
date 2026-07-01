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
$wallet_uuid = $data['wallet_uuid'] ?? null;

// التحقق من صحة المدخلات
if (!$wallet_uuid) {
    respond('error', 'wallet_uuid is required');
}

// استرجاع المعاملات للمحفظة المعطاة
$query = "SELECT * FROM transactions WHERE wallet_uuid = ? ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([$wallet_uuid]);

$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// التحقق من وجود معاملات
if ($transactions) {
    respond('success', 'Transactions retrieved', $transactions);
} else {
    respond('error', 'No transactions found for this wallet');
}
?>
