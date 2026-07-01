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
if (!isset($data['platform_uuid'])) {
    respond('error', 'platform_uuid is required');
}

$platform_uuid = $data['platform_uuid'];

// توليد UUID جديد للمحفظة
$wallet_uuid = generate_uuid();

// إدخال المحفظة الجديدة في قاعدة البيانات
$query = "INSERT INTO wallets (uuid, platform_uuid, balance) VALUES (?, ?, 0.00)";
$stmt = $pdo->prepare($query);
$stmt->execute([$wallet_uuid, $platform_uuid]);
// الحصول على الـ id الذي تم إدخاله
$wallet_id = $pdo->lastInsertId();

// التحقق من نجاح العملية
if ($stmt->rowCount() > 0) {
    // استرجاع UUID للمنصة بناءً على API Key
    $query = "SELECT uuid FROM wallets WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$wallet_id]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    respond('success', 'Wallet created', ['wallet_uuid' => $wallet['uuid']]);
} else {
    respond('error', 'Failed to create wallet');
}
?>
