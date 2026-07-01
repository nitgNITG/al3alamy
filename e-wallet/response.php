<?php
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

// التحقق من وجود tap_id في الطلب
if (isset($_GET['tap_id'])) {
    $charge_id = $_GET['tap_id'];

    // الحصول على بيانات المحفظة للمستخدم الحالي
    $user_wallet = $DB->get_record('user_wallet', ['user_id' => $USER->id]);
    if (!$user_wallet) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User wallet not found']);
        exit;
    }
    $uuid = $user_wallet->wallet_uuid;

    try {
        // طلب بيانات الشحنة من Tap API
        $response = $client->request('GET', "https://api.tap.company/v2/charges/$charge_id", [
            'headers' => [
                'Authorization' => 'Bearer sk_test_XKokBfNWv6FIYuTMg5sLPjhJ',
                'accept' => 'application/json',
            ],
        ]);

        $responseData = json_decode($response->getBody()->getContents(), true);

        // التحقق من حالة الشحنة
        if ($response->getStatusCode() === 200 && isset($responseData['status'])) {
            $status = $responseData['status'];
            if ($status === 'CAPTURED') {
                $amount = (int) $responseData['amount'];

                // شحن المحفظة عبر API خارجي
                $walletResponse = $client->request('POST', 'https://salem-mar3y.com/e-wallet/src/api/recharge_wallet.php', [
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

                if ($walletResponse->getStatusCode() === 200) {
                    \core\notification::add('Wallet recharged successfully.', \core\output\notification::NOTIFY_SUCCESS);
                } else {
                    \core\notification::add('Failed to recharge wallet.', \core\output\notification::NOTIFY_ERROR);
                }
            } elseif ($status === 'INITIATED') {
                // توجيه المستخدم لإكمال الدفع
                $redirectUrl = $responseData['transaction']['url'];
                \core\notification::add('Charge is still in the INITIATED state. Please complete the payment process <a href="' . $redirectUrl . '">here</a>.', \core\output\notification::NOTIFY_WARNING);
            } elseif ($status === 'FAILED') {
                // معالجة حالة الفشل
                \core\notification::add('Charge failed. Please try again or use a different payment method.', \core\output\notification::NOTIFY_ERROR);
            } else {
                \core\notification::add('Charge status is not recognized: ' . $status, \core\output\notification::NOTIFY_ERROR);
            }
        } else {
            \core\notification::add('Failed to fetch charge details.', \core\output\notification::NOTIFY_ERROR);
        }
    } catch (RequestException $e) {
        \core\notification::add('Error: ' . $e->getMessage(), \core\output\notification::NOTIFY_ERROR);
    }

    // إعادة توجيه المستخدم بعد معالجة الشحنة
    redirect(new moodle_url('/e-wallet'));
} else {
    // معالجة حالة عدم وجود معرف الشحنة
    http_response_code(400);
    \core\notification::add('Charge ID not provided.', \core\output\notification::NOTIFY_ERROR);
    redirect(new moodle_url('/e-wallet'));
}
