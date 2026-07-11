<?php
/**
 * Kashier server-to-server webhook.
 *
 * Kashier POSTs payment notifications here (independent of the browser redirect).
 * We use it for extra reliability — if the user closes the tab before the
 * browser redirect fires, the webhook still completes the enrollment.
 *
 * Body (JSON):
 *   {
 *     "merchantOrderId": "vid-...",
 *     "paymentStatus": "SUCCESS",
 *     "amount": "250.00",
 *     "currency": "EGP",
 *     "hash": "...",
 *     "transactionId": "..."
 *   }
 */

// Webhook runs outside normal Moodle session — bootstrap manually.
define('CLI_SCRIPT', false);
define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/config.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/local/registrationcodes/classes/manager.php');

global $DB, $CFG;

// Only accept POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    exit('Bad Request');
}

$order_id       = $data['merchantOrderId'] ?? '';
$payment_status = $data['paymentStatus']   ?? '';
$amount_str     = $data['amount']          ?? '0';
$received_hash  = $data['hash']            ?? '';
$transaction_id = $data['transactionId']   ?? '';

// Verify HMAC.
if (!kashier_verify_hash($order_id, $amount_str, $received_hash)) {
    error_log("kashier/webhook.php: HMAC mismatch for order $order_id");
    http_response_code(403);
    exit('Forbidden');
}

if (strtoupper($payment_status) !== 'SUCCESS') {
    http_response_code(200);
    exit('OK — not success');
}

// Replay protection.
if ($DB->record_exists('kashier_transactions', ['order_id' => $order_id, 'status' => 'success'])) {
    http_response_code(200);
    exit('OK — already processed');
}

$amount = (float)$amount_str;

if (strpos($order_id, 'vid-') === 0) {
    $parts    = explode('-', $order_id);
    $pay_uid  = isset($parts[1]) ? (int)$parts[1] : 0;
    $courseid = isset($parts[2]) ? (int)$parts[2] : 0;
    $groupid  = isset($parts[3]) ? (int)$parts[3] : 0;

    if (!$pay_uid || !$courseid || !$groupid) {
        http_response_code(400);
        exit('Bad order reference');
    }

    $DB->insert_record('kashier_transactions', [
        'order_id'       => $order_id,
        'transaction_id' => $transaction_id,
        'user_id'        => $pay_uid,
        'amount'         => $amount,
        'currency'       => KASHIER_CURRENCY,
        'type'           => 'video',
        'status'         => 'success',
        'timecreated'    => time(),
    ]);

    // Enroll.
    $course_context = context_course::instance($courseid);
    if (!is_enrolled($course_context, $pay_uid)) {
        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual', 'status' => 0]);
        if ($instance) {
            $enrol_plugin = enrol_get_plugin('manual');
            $student_role = $DB->get_record('role', ['shortname' => 'student']);
            $enrol_plugin->enrol_user($instance, $pay_uid, $student_role ? (int)$student_role->id : 5);
        }
    }

    // Add to group.
    if (!$DB->record_exists('groups_members', ['userid' => $pay_uid, 'groupid' => $groupid])) {
        groups_add_member($groupid, $pay_uid);
    }

} elseif (strpos($order_id, 'dep-') === 0) {
    $parts   = explode('-', $order_id);
    $pay_uid = isset($parts[1]) ? (int)$parts[1] : 0;

    if (!$pay_uid) {
        http_response_code(400);
        exit('Bad deposit reference');
    }

    $DB->insert_record('kashier_transactions', [
        'order_id'       => $order_id,
        'transaction_id' => $transaction_id,
        'user_id'        => $pay_uid,
        'amount'         => $amount,
        'currency'       => KASHIER_CURRENCY,
        'type'           => 'deposit',
        'status'         => 'success',
        'timecreated'    => time(),
    ]);

    $wallet_uuid = $DB->get_field('user_wallet', 'wallet_uuid', ['user_id' => $pay_uid]);
    if ($wallet_uuid) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://salem-mar3y.com/e-wallet/src/api/recharge_wallet.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'wallet_uuid' => $wallet_uuid,
            'amount'      => $amount,
            'description' => 'Kashier deposit (webhook)',
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer 8b5a0e6d266ae2c3250a98ac3a568a95',
            'Content-Type: application/json',
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
} elseif (strpos($order_id, 'codes-') === 0) {
    // Registration-code purchase — format: codes-{userid}-{count}-{timestamp}
    $parts     = explode('-', $order_id);
    $pay_uid   = isset($parts[1]) ? (int) $parts[1] : 0;
    $req_count = isset($parts[2]) ? (int) $parts[2] : 0;

    if (!$pay_uid || $req_count < 1) {
        http_response_code(400);
        exit('Bad codes order reference');
    }

    $DB->insert_record('kashier_transactions', [
        'order_id'       => $order_id,
        'transaction_id' => $transaction_id,
        'user_id'        => $pay_uid,
        'amount'         => $amount,
        'currency'       => KASHIER_CURRENCY,
        'type'           => 'codes',
        'status'         => 'success',
        'timecreated'    => time(),
    ]);

    // Generate codes with the order_id tag so codes_ready.php can look them up.
    $notes_tag = 'kashier-order:' . $order_id;
    \local_registrationcodes\manager::generate_codes(
        $req_count,
        '',
        null,
        $notes_tag,
        $pay_uid
    );
}

http_response_code(200);
echo 'OK';
