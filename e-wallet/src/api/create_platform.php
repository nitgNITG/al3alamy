<?php
require '../config/db.php';
require '../config/auth.php';
require '../utils/helpers.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['name']) || empty($data['name'])) {
    respond('error', 'Platform name is required');
}

$name = $data['name'];

// التحقق مما إذا كانت المنصة موجودة مسبقاً
$query = "SELECT * FROM platforms WHERE name = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$name]);

if ($stmt->rowCount() > 0) {
    respond('error', 'Platform already exists');
}

// إنشاء UUID و API Key
$uuid = generate_uuid();
$api_key = generateApiKey();

// إنشاء المنصة الجديدة
$query = "INSERT INTO platforms (uuid, name, api_key) VALUES (?, ?, ?)";
$stmt = $pdo->prepare($query);
$stmt->execute([$uuid, $name, $api_key]);

if ($stmt->rowCount() > 0) {
    // استرجاع UUID للمنصة بناءً على API Key
    $query = "SELECT uuid FROM platforms WHERE api_key = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$api_key]);
    $platform = $stmt->fetch(PDO::FETCH_ASSOC);

    respond('success', ['message' => 'Platform created', 'platform_uuid' => $platform['uuid'], 'api_key' => $api_key]);
} else {
    respond('error', 'Failed to create platform');
}
?>
