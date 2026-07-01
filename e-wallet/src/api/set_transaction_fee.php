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
$transaction_type = $data['transaction_type'] ?? null;
$fee_type = $data['fee_type'] ?? null; // 'fixed' أو 'percentage'
$fee_value = $data['fee_value'] ?? null;

if (!$transaction_type || !$fee_type || !is_numeric($fee_value)) {
    respond('error', 'Invalid input');
}

try {
    // التحقق من وجود رسوم مسبقة لنفس نوع العملية والمنصة
    $query = "SELECT id FROM transaction_fees WHERE platform_uuid = ? AND transaction_type = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$platform_uuid, $transaction_type]);

    if ($stmt->rowCount() > 0) {
        // تحديث الرسوم الحالية
        $query = "UPDATE transaction_fees SET fee_type = ?, fee_value = ? WHERE platform_uuid = ? AND transaction_type = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$fee_type, $fee_value, $platform_uuid, $transaction_type]);

        respond('success', 'Transaction fee updated successfully');
    } else {
        // إضافة رسوم جديدة
        $query = "INSERT INTO transaction_fees (platform_uuid, transaction_type, fee_type, fee_value) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$platform_uuid, $transaction_type, $fee_type, $fee_value]);

        respond('success', 'Transaction fee set successfully');
    }
} catch (PDOException $e) {
    respond('error', 'Database error: ' . $e->getMessage());
}
?>
