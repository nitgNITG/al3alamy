<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$client = new Client();
global $DB, $USER, $CFG;

// تحقق من تسجيل الدخول وعدم كونه مستخدم ضيف
if (!isloggedin() || isguestuser()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // الحصول على القيم من النموذج
    $uuid = $_POST['uuid'];
    $amount = $_POST['amount'];

    // التحقق من صحة البيانات
    if (empty($uuid) || empty($amount)) {
        echo 'All fields are required.';
        exit;
    }

    // إعداد عميل Guzzle
    $client = new Client();

    try {
        // إرسال طلب شحن المحفظة
        $response = $client->request('POST', 'https://salem-mar3y.com/e-wallet/src/api/recharge_wallet.php', [
            'headers' => [
                'Authorization' => 'Bearer 8b5a0e6d266ae2c3250a98ac3a568a95',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'wallet_uuid' => $uuid,
                'amount' => $amount,
                'description' => 'Recharged wallet',
            ],
        ]);


        // التحقق من نجاح العملية
        if ($response->getStatusCode() === 200) {
            \core\notification::add('Wallet recharged successfully.', \core\output\notification::NOTIFY_SUCCESS);
        } else {
            \core\notification::add('Failed to recharge wallet.', \core\output\notification::NOTIFY_ERROR);
        }        
    } catch (Exception $e) {
        // التعامل مع الأخطاء
        \core\notification::add('An error occurred: ' . $e->getMessage(), \core\output\notification::NOTIFY_ERROR);
    }

    redirect(new moodle_url('/e-wallet'));
}
?>
