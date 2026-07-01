<?php
function respond($status, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function generate_uuid() {
    // توليد 16 بايت عشوائي
    $data = random_bytes(16);

    // تعيين الإصدار إلى 4 (الـ UUID العشوائي)
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    // صيغة الـ UUID القياسي
    return vsprintf('%s-%s-%s-%s-%s', str_split(bin2hex($data), 4));
}

// دالة توليد API Key
function generateApiKey() {
    return bin2hex(random_bytes(16)); // توليد 32 حرفًا (128 بت)
}
?>
