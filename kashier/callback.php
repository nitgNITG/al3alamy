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

global $DB, $CFG, $USER;

// ── Parse Kashier params (support both session and legacy param names) ─────
$order_id       = optional_param('orderId',          '', PARAM_RAW)
               ?: optional_param('merchantOrderId',  '', PARAM_RAW);
$payment_status = optional_param('paymentStatus',    '', PARAM_ALPHA);
$session_id     = optional_param('sessionId',        '', PARAM_RAW);
$amount_str     = optional_param('amount',           '0', PARAM_RAW);
$received_hash  = optional_param('hash',             '', PARAM_RAW);
$transaction_id = optional_param('transactionId',    '', PARAM_RAW);

if (!$order_id || !$payment_status) {
    \core\notification::add('Invalid callback parameters.', \core\output\notification::NOTIFY_ERROR);
    redirect(new moodle_url('/'));
}

$account_type = kashier_account_for_order($order_id);

// ── Verification — session API (new) or hash (legacy) ─────────────────────
if ($session_id) {
    // ── Session-based: verify via Kashier GET API ──────────────────────────
    $verified = kashier_verify_session($session_id, $account_type);
    $verified_status = strtoupper($verified['paymentStatus'] ?? $verified['status'] ?? '');

    if ($verified_status !== 'SUCCESS') {
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
        $transaction_id = $verified['transactionId'] ?? $verified['_id'] ?? '';
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

// ── Route by order type ───────────────────────────────────────────────────
if (strpos($order_id, 'vid-') === 0) {
    // Format: vid-{userid}-{courseid}-{groupid}-{cmid}-{timestamp}
    $parts    = explode('-', $order_id);
    // parts: [0]=vid [1]=userid [2]=courseid [3]=groupid [4]=cmid [5]=timestamp
    $pay_uid  = isset($parts[1]) ? (int)$parts[1] : 0;
    $courseid = isset($parts[2]) ? (int)$parts[2] : 0;
    $groupid  = isset($parts[3]) ? (int)$parts[3] : 0;
    $cmid     = isset($parts[4]) ? (int)$parts[4] : 0;

    if (!$pay_uid || !$courseid || !$groupid) {
        \core\notification::add('Invalid order reference.', \core\output\notification::NOTIFY_ERROR);
        redirect(new moodle_url('/'));
    }

    // Security: must be the same logged-in user (or we use pay_uid directly).
    $userid = $USER->id ?: $pay_uid;
    if ($USER->id && $USER->id !== $pay_uid) {
        \core\notification::add('User mismatch in payment.', \core\output\notification::NOTIFY_ERROR);
        redirect(new moodle_url('/'));
    }

    // Record transaction.
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

    // ── Enroll in course ────────────────────────────────────────────────
    $course_context = context_course::instance($courseid);
    if (!is_enrolled($course_context, $pay_uid)) {
        $instance = $DB->get_record('enrol', [
            'courseid' => $courseid,
            'enrol'    => 'manual',
            'status'   => 0,
        ]);
        if ($instance) {
            $enrol_plugin = enrol_get_plugin('manual');
            $student_role = $DB->get_record('role', ['shortname' => 'student']);
            $roleid       = $student_role ? (int)$student_role->id : 5;
            $enrol_plugin->enrol_user($instance, $pay_uid, $roleid);
        }
    }

    // ── Add to group (unlocks video + associated quizzes) ───────────────
    if (!$DB->record_exists('groups_members', ['userid' => $pay_uid, 'groupid' => $groupid])) {
        groups_add_member($groupid, $pay_uid);
    }

    \core\notification::add(
        'تم الدفع بنجاح! تم فتح الفيديو. Payment successful — video unlocked.',
        \core\output\notification::NOTIFY_SUCCESS
    );
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));

} elseif (strpos($order_id, 'dep-') === 0) {
    // Wallet deposit — format: dep-{userid}-{amount_egp}-{timestamp}
    $parts   = explode('-', $order_id);
    $pay_uid = isset($parts[1]) ? (int)$parts[1] : 0;

    if (!$pay_uid) {
        \core\notification::add('Invalid deposit reference.', \core\output\notification::NOTIFY_ERROR);
        redirect(new moodle_url('/e-wallet/'));
    }

    // Record transaction.
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

    // Recharge the e-wallet via the existing external API.
    $wallet_api_key = '8b5a0e6d266ae2c3250a98ac3a568a95';
    $wallet_uuid    = $DB->get_field('user_wallet', 'wallet_uuid', ['user_id' => $pay_uid]);

    if ($wallet_uuid) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://salem-mar3y.com/e-wallet/src/api/recharge_wallet.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'wallet_uuid' => $wallet_uuid,
            'amount'      => $amount,
            'description' => 'Kashier deposit',
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $wallet_api_key,
            'Content-Type: application/json',
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    \core\notification::add(
        'تم شحن المحفظة بنجاح! Wallet topped up successfully.',
        \core\output\notification::NOTIFY_SUCCESS
    );
    redirect(new moodle_url('/e-wallet/'));

} elseif (strpos($order_id, 'codes-') === 0) {
    // Registration-code purchase — format: codes-{userid}-{count}-{timestamp}
    $parts    = explode('-', $order_id);
    $pay_uid  = isset($parts[1]) ? (int) $parts[1] : 0;
    $req_count = isset($parts[2]) ? (int) $parts[2] : 0;

    if (!$pay_uid || $req_count < 1) {
        \core\notification::add('Invalid codes order reference.', \core\output\notification::NOTIFY_ERROR);
        redirect(new moodle_url('/'));
    }

    // Security: logged-in user must match the order owner.
    if ($USER->id && $USER->id !== $pay_uid) {
        \core\notification::add('User mismatch in codes payment.', \core\output\notification::NOTIFY_ERROR);
        redirect(new moodle_url('/'));
    }

    // Record transaction (replay-safe — already checked above).
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

    // Generate codes (notes tag lets codes_ready.php retrieve them by order).
    $notes_tag = 'kashier-order:' . $order_id;
    \local_registrationcodes\manager::generate_codes(
        $req_count,
        '',       // no prefix
        null,     // no expiry
        $notes_tag,
        $pay_uid
    );

    \core\notification::add(
        'تم الدفع بنجاح! جاري عرض الأكواد… Payment successful — generating codes.',
        \core\output\notification::NOTIFY_SUCCESS
    );
    redirect(new moodle_url('/local/registrationcodes/codes_ready.php', ['order_id' => $order_id]));

} elseif (strpos($order_id, 'sub-') === 0) {
    // Subscription purchase — format: sub-{userid}-{planid}-{timestamp}
    $parts   = explode('-', $order_id);
    $pay_uid = isset($parts[1]) ? (int) $parts[1] : 0;
    $planid  = isset($parts[2]) ? (int) $parts[2] : 0;

    if (!$pay_uid || !$planid) {
        \core\notification::add('Invalid subscription order reference.', \core\output\notification::NOTIFY_ERROR);
        redirect(new moodle_url('/local/subscriptions/index.php'));
    }

    // Security: logged-in user must match the order owner.
    if ($USER->id && $USER->id !== $pay_uid) {
        \core\notification::add('User mismatch in subscription payment.', \core\output\notification::NOTIFY_ERROR);
        redirect(new moodle_url('/local/subscriptions/index.php'));
    }

    // Record transaction (replay-safe — already checked above).
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

    // Activate the subscription (skip if the user is somehow already subscribed).
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

    \core\notification::add(
        get_string('payment_success', 'local_subscriptions'),
        \core\output\notification::NOTIFY_SUCCESS
    );
    redirect(new moodle_url('/local/subscriptions/mysubscriptions.php'));

} else {
    \core\notification::add('Unknown payment type.', \core\output\notification::NOTIFY_ERROR);
    redirect(new moodle_url('/'));
}
