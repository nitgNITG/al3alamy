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
$transaction_uuid = $data['transaction_uuid'] ?? null;

if (!$transaction_uuid) {
    respond('error', 'Transaction UUID is required');
}

try {
    // التحقق من وجود المعاملة
    $query = "SELECT id, type, status FROM transactions WHERE uuid = ? AND platform_uuid = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$transaction_uuid, $platform_uuid]);

    if ($stmt->rowCount() === 0) {
        respond('error', 'Transaction not found or does not belong to this platform');
    }

    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    // تحقق من الحالة
    if ($transaction['status'] !== 'pending') {
        respond('error', 'Transaction cannot be canceled');
    }

    // تحديث حالة المعاملة إلى 'canceled'
    $query = "UPDATE transactions SET status = 'canceled' WHERE uuid = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$transaction_uuid]);

    respond('success', 'Transaction canceled successfully');
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    respond('error', 'Database error: ' . $e->getMessage());
}
?>
