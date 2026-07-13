<?php
/**
 * Kashier server-to-server webhook.
 *
 * Session-based (codes-):  JSON body contains sessionId → verified via GET API.
 * Legacy redirect (vid-/dep-): JSON body contains hash   → verified via HMAC.
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

error_log('kashier/webhook.php body: ' . $raw);

if (!$data) {
    http_response_code(400);
    exit('Bad Request');
}

// Kashier may nest the payload under 'data'.
$payload = $data['data'] ?? $data;

$order_id       = $payload['orderId']       ?? $payload['merchantOrderId'] ?? '';
$payment_status = $payload['paymentStatus'] ?? $payload['status']          ?? '';
$session_id     = $payload['sessionId']     ?? $payload['_id']             ?? '';
$amount_str     = $payload['amount']        ?? '0';
$received_hash  = $payload['hash']          ?? '';
$transaction_id = $payload['transactionId'] ?? '';

if (!$order_id && !$session_id) {
    http_response_code(400);
    exit('Bad Request — missing orderId/sessionId');
}

$account_type = $order_id ? kashier_account_for_order($order_id) : 'student';

// ── Verification ──────────────────────────────────────────────────────────
if ($session_id) {
    // Session-based: verify via Kashier API.
    $verified = kashier_verify_session($session_id, $account_type);

    // Recover the merchant order id from the verified session if the webhook
    // body didn't carry it.
    if (!$order_id && !empty($verified['merchantOrderId'])) {
        $order_id     = $verified['merchantOrderId'];
        $account_type = kashier_account_for_order($order_id);
    }

    if (!kashier_session_is_paid($verified)) {
        http_response_code(200);
        exit('OK — not success');
    }
    if (!$amount_str || $amount_str === '0') {
        $amount_str = (string) ($verified['amount'] ?? '0');
    }
    if (!$transaction_id) {
        $transaction_id = $verified['transactionId'] ?? $verified['orderId'] ?? $verified['sessionId'] ?? '';
    }

} else {
    // Legacy: verify HMAC.
    if (!kashier_verify_hash($order_id, $amount_str, $received_hash, $account_type)) {
        error_log("kashier/webhook.php: HMAC mismatch for order $order_id");
        http_response_code(403);
        exit('Forbidden');
    }
    if (strtoupper($payment_status) !== 'SUCCESS') {
        http_response_code(200);
        exit('OK — not success');
    }
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
} elseif (strpos($order_id, 'sub-') === 0) {
    // Subscription purchase — format: sub-{userid}-{planid}-{timestamp}
    $parts   = explode('-', $order_id);
    $pay_uid = isset($parts[1]) ? (int) $parts[1] : 0;
    $planid  = isset($parts[2]) ? (int) $parts[2] : 0;

    if (!$pay_uid || !$planid) {
        http_response_code(400);
        exit('Bad subscription order reference');
    }

    $DB->insert_record('kashier_transactions', [
        'order_id'       => $order_id,
        'transaction_id' => $transaction_id,
        'user_id'        => $pay_uid,
        'amount'         => $amount,
        'currency'       => KASHIER_CURRENCY,
        'type'           => 'subscription',
        'status'         => 'success',
        'timecreated'    => time(),
    ]);

    // Activate subscription — requires local/subscriptions plugin (pending build).
    if (class_exists('\local_subscriptions\manager')) {
        if (!\local_subscriptions\manager::has_active_subscription($pay_uid)) {
            \local_subscriptions\manager::activate_for_user(
                $planid,
                $pay_uid,
                $amount,
                \local_subscriptions\manager::SOURCE_ONLINE,
                $order_id,
                $transaction_id
            );
        }
    } else {
        error_log("kashier/webhook.php: local_subscriptions plugin missing — sub order $order_id recorded but not activated");
    }
}

http_response_code(200);
echo 'OK';
