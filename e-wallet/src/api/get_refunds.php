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
$start_date = $data['start_date'] ?? null;
$end_date = $data['end_date'] ?? null;
$wallet_uuid = $data['wallet_uuid'] ?? null;

// التحقق من صحة المدخلات
if (!$start_date || !$end_date) {
    respond('error', 'Start date and end date are required');
}

// بناء استعلام SQL
$query = "SELECT * FROM transactions WHERE is_refund = 1 AND created_at BETWEEN ? AND ?";

$params = [$start_date, $end_date];

// إضافة شرط للمحفظة إذا تم تحديده
if ($wallet_uuid) {
    $query .= " AND wallet_uuid = ?";
    $params[] = $wallet_uuid;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);

$refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إرسال سجل الاسترجاع كاستجابة
respond('success', 'Refunds retrieved', $refunds);
?>
