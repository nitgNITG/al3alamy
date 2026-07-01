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
if (!isset($data['wallet_uuid']) || !isset($data['action'])) {
    respond('error', 'wallet_uuid and action are required');
}

$wallet_uuid = $data['wallet_uuid'];
$action = $data['action']; // يمكن أن تكون "freeze" أو "unfreeze"

// التحقق من صحة الإجراء
if ($action !== 'freeze' && $action !== 'unfreeze') {
    respond('error', 'Invalid action');
}

// التحقق من وجود المحفظة
$query = "SELECT id, status FROM wallets WHERE uuid = ? AND platform_uuid = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$wallet_uuid, $platform_uuid]);

if ($stmt->rowCount() === 0) {
    respond('error', 'Wallet not found or does not belong to this platform');
}

$wallet = $stmt->fetch(PDO::FETCH_ASSOC);

// تنفيذ الإجراء المطلوب (تجميد أو إلغاء تجميد)
$new_status = ($action === 'freeze') ? 'frozen' : 'active';

$query = "UPDATE wallets SET status = ? WHERE uuid = ?";
$stmt = $pdo->prepare($query);
$updated = $stmt->execute([$new_status, $wallet_uuid]);

if ($updated) {
    respond('success', 'Wallet status updated successfully', ['new_status' => $new_status]);
} else {
    respond('error', 'Failed to update wallet status');
}
?>
