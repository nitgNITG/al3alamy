<?php
/**
 * Kashier callback — called by Kashier after payment (GET redirect).
 *
 * Session-based flow (codes- orders):
 *   Kashier sends: orderId, paymentStatus, sessionId, transactionId
 *   Verification: server-to-server GET /v3/payment/sessions/{id}/payment
 *
 * Legacy redirect flow (vid- / dep- orders):
 *   Kashier sends: merchantOrderId, paymentStatus, amount, hash, transactionId
 *   Verification: HMAC-SHA256 hash check
 */

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/config.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/local/registrationcodes/classes/manager.php');

global $DB, $CFG, $USER, $SESSION;

// ── Parse Kashier params (support both session and legacy param names) ─────
$k_order        = optional_param('k_order',          '', PARAM_RAW); // our own marker
$order_id       = optional_param('orderId',          '', PARAM_RAW)
               ?: optional_param('merchantOrderId',  '', PARAM_RAW)
               ?: optional_param('orderReference',   '', PARAM_RAW)
               ?: $k_order;
$payment_status = optional_param('paymentStatus',    '', PARAM_ALPHA);
$session_id     = optional_param('sessionId',        '', PARAM_RAW);
$amount_str     = optional_param('amount',           '0', PARAM_RAW);
$received_hash  = optional_param('hash',             '', PARAM_RAW);
$transaction_id = optional_param('transactionId',    '', PARAM_RAW);

error_log('kashier/callback.php GET: ' . json_encode($_GET, JSON_UNESCAPED_SLASHES));

// Fall back to the pending purchase saved when the session was created — the
// session redirect does not reliably echo our order/session ids.
$pending = $SESSION->kashier_pending_video ?? null;
if (!$order_id && !empty($pending['order_id'])) {
    $order_id = $pending['order_id'];
}
// Recover the Kashier session id: from the redirect, the DB pending row (keyed
// by our order id), or the session backup — in that order.
if (!$session_id && $order_id) {
    $session_id = kashier_lookup_session_id($order_id);
}
if (!$session_id && !empty($pending['sessionId'])) {
    $session_id = $pending['sessionId'];
}

if (!$order_id && !$session_id) {
    \core\notification::add('Invalid callback parameters.', \core\output\notification::NOTIFY_ERROR);
    redirect(new moodle_url('/'));
}

$account_type = $order_id ? kashier_account_for_order($order_id) : 'student';

// ── Verification — session API (new) or hash (legacy) ─────────────────────
if ($session_id) {
    // ── Session-based: verify via Kashier GET API ──────────────────────────
    $verified = kashier_verify_session($session_id, $account_type);

    // Recover the merchant order id straight from the verified session if the
    // redirect didn't carry it.
    if (!$order_id && !empty($verified['merchantOrderId'])) {
        $order_id     = $verified['merchantOrderId'];
        $account_type = kashier_account_for_order($order_id);
    }

    if (!kashier_session_is_paid($verified)) {
        $verified_status = strtoupper((string)($verified['status'] ?? $verified['paymentStatus'] ?? '?'));
        error_log("kashier/callback.php: session $session_id status=$verified_status for order $order_id");
        \core\notification::add(
            'لم تكتمل عملية الدفع. Payment was not completed (' . $verified_status . ').',
            \core\output\notification::NOTIFY_WARNING
        );
        redirect(new moodle_url('/'));
    }

    // Use amount from verified session if not in GET params.
    if (!$amount_str || $amount_str === '0') {
        $amount_str = (string) ($verified['amount'] ?? '0');
    }
    if (!$transaction_id) {
        $transaction_id = $verified['transactionId'] ?? $verified['orderId'] ?? $verified['sessionId'] ?? '';
    }

} else {
    // ── Legacy redirect: verify HMAC ──────────────────────────────────────
    if (!kashier_verify_hash($order_id, $amount_str, $received_hash, $account_type)) {
        error_log("kashier/callback.php: HMAC mismatch for order $order_id");
        \core\notification::add('Payment verification failed.', \core\output\notification::NOTIFY_ERROR);
        redirect(new moodle_url('/'));
    }

    if (strtoupper($payment_status) !== 'SUCCESS') {
        \core\notification::add(
            'لم تكتمل عملية الدفع. Payment was not completed (' . $payment_status . ').',
            \core\output\notification::NOTIFY_WARNING
        );
        redirect(new moodle_url('/'));
    }
}

// ── Replay protection ─────────────────────────────────────────────────────
if ($DB->record_exists('kashier_transactions', ['order_id' => $order_id, 'status' => 'success'])) {
    \core\notification::add('This payment has already been processed.', \core\output\notification::NOTIFY_INFO);
    // For codes orders, still redirect to the ready page so manager can see their codes.
    if ($account_type === 'manager') {
        redirect(new moodle_url('/local/registrationcodes/codes_ready.php', ['order_id' => $order_id]));
    }
    redirect(new moodle_url('/'));
}

$amount = (float)$amount_str;

// Security: a logged-in user may only complete their own order.
// Cast both sides to int — $USER->id may be a string from the session, and a
// strict comparison would wrongly reject the legitimate buyer.
$order_uid = (int)(explode('-', $order_id)[1] ?? 0);
// If user is not logged in, try to log them in using the user ID from the order
if (!isloggedin() && $order_uid > 0) {
    $order_user = $DB->get_record('user', ['id' => $order_uid, 'deleted' => 0], '*', IGNORE_MISSING);
    if ($order_user) {
        // Set the user session for the order owner
        complete_user_login($order_user);
        \core\session\manager::set_user($order_user);
        $USER = $order_user;
    }
}
// Now check if the logged-in user matches the order
if ((int)$USER->id && $order_uid && (int)$USER->id !== $order_uid) {
    error_log("kashier/callback.php: user mismatch USER->id=" . $USER->id
        . " order_uid=$order_uid order=$order_id");
    \core\notification::add('User mismatch in payment.', \core\output\notification::NOTIFY_ERROR);
    redirect(new moodle_url('/'));
}

// ── Fulfil (enrol/group/wallet/codes/sub) via the shared idempotent path ───
$res = kashier_fulfill_order($order_id, $transaction_id, $amount);

if (!$res['valid']) {
    \core\notification::add('Invalid order reference.', \core\output\notification::NOTIFY_ERROR);
    redirect(new moodle_url('/'));
}

unset($SESSION->kashier_pending_video);

// ── Redirect to the right place for the order type ─────────────────────────
if ($res['type'] === 'video') {
    \core\notification::add(
        'تم الدفع بنجاح! تم فتح الفيديو. Payment successful — video unlocked.',
        \core\output\notification::NOTIFY_SUCCESS
    );
    // Jump straight to the purchased module when we know it.
    if (!empty($res['cmid'])) {
        $cm = $DB->get_record('course_modules', ['id' => $res['cmid']]);
        if ($cm) {
            $module = $DB->get_field('modules', 'name', ['id' => $cm->module]);
            redirect(new moodle_url('/mod/' . $module . '/view.php', ['id' => $res['cmid']]));
        }
    }
    redirect(new moodle_url('/course/view.php', ['id' => $res['courseid']]));

} elseif ($res['type'] === 'deposit') {
    \core\notification::add(
        'تم شحن المحفظة بنجاح! Wallet topped up successfully.',
        \core\output\notification::NOTIFY_SUCCESS
    );
    redirect(new moodle_url('/e-wallet/'));

} elseif ($res['type'] === 'codes') {
    \core\notification::add(
        'تم الدفع بنجاح! جاري عرض الأكواد… Payment successful — generating codes.',
        \core\output\notification::NOTIFY_SUCCESS
    );
    redirect(new moodle_url('/local/registrationcodes/codes_ready.php', ['order_id' => $order_id]));

} elseif ($res['type'] === 'subscription') {
    if (class_exists('\local_subscriptions\manager')) {
        \core\notification::add(
            get_string('payment_success', 'local_subscriptions'),
            \core\output\notification::NOTIFY_SUCCESS
        );
        redirect(new moodle_url('/local/subscriptions/mysubscriptions.php'));
    }
    error_log("kashier/callback.php: local_subscriptions plugin missing — sub order $order_id recorded but not activated");
    \core\notification::add(
        'تم استلام الدفع وسيتم تفعيل الاشتراك قريباً. Payment received — subscription will be activated shortly.',
        \core\output\notification::NOTIFY_WARNING
    );
    redirect(new moodle_url('/'));
}

\core\notification::add('Unknown payment type.', \core\output\notification::NOTIFY_ERROR);
redirect(new moodle_url('/'));
