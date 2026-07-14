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

// Prefer OUR merchant order reference (e.g. "sub-123-4-...", "vid-123-...") over
// Kashier's internal `orderId`; using Kashier's id makes fulfilment fail with
// "Bad order reference". See kashier/callback.php for the full reasoning.
$order_candidates = array_values(array_filter([
    $payload['merchantOrderId'] ?? '',
    $payload['orderReference']  ?? '',
    $payload['orderId']         ?? '',
]));
$order_id = '';
foreach ($order_candidates as $order_candidate) {
    if (preg_match('/^(sub|vid|dep|codes)-/', (string)$order_candidate)) {
        $order_id = (string)$order_candidate;
        break;
    }
}
if ($order_id === '' && !empty($order_candidates)) {
    $order_id = (string)$order_candidates[0];
}
$payment_status = $payload['paymentStatus'] ?? $payload['status']          ?? '';
$session_id     = $payload['sessionId']     ?? $payload['_id']             ?? '';
$amount_str     = $payload['amount']        ?? '0';
$received_hash  = $payload['hash']          ?? '';
$transaction_id = $payload['transactionId'] ?? '';

if (!$order_id && !$session_id) {
    http_response_code(400);
    exit('Bad Request — missing orderId/sessionId');
}

// Recover the Kashier session id from the pending row if the body lacked it.
if (!$session_id && $order_id) {
    $session_id = kashier_lookup_session_id($order_id);
}

$account_type = $order_id ? kashier_account_for_order($order_id) : 'student';

// ── Verification ──────────────────────────────────────────────────────────
if ($session_id) {
    // Session-based: verify via Kashier API.
    $verified = kashier_verify_session($session_id, $account_type);

    // Recover the merchant order id from the verified session if the webhook
    // body didn't carry it — or carried only Kashier's internal id.
    if (!preg_match('/^(sub|vid|dep|codes)-/', (string)$order_id)) {
        $recovered = $verified['merchantOrderId'] ?? $verified['order'] ?? '';
        if ($recovered !== '') {
            $order_id     = $recovered;
            $account_type = kashier_account_for_order($order_id);
        }
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

// Fulfil via the shared idempotent path (enrol/group, wallet, codes, sub).
$res = kashier_fulfill_order($order_id, $transaction_id, $amount);

if (!$res['valid']) {
    error_log("kashier/webhook.php: unfulfillable order reference $order_id");
    http_response_code(400);
    exit('Bad order reference');
}

http_response_code(200);
echo 'OK';
