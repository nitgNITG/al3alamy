<?php
/**
 * Kashier — pay for a specific video / course module.
 *
 * Accepts GET params:
 *   cmid     (int)  – course module ID (the locked video/resource)
 *   groupid  (int)  – Moodle group that gates this content
 *   courseid (int)  – course ID (for redirect after payment)
 *   amount   (int)  – price in EGP (whole number, e.g. 150)
 *
 * Flow:
 *   Student clicks "Buy" → this page → Kashier checkout → kashier/callback.php
 *   → enroll + add to group → redirect to course
 */

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/config.php');

global $DB, $CFG, $USER;

if (!isloggedin() || isguestuser()) {
    redirect(get_login_url());
}

$cmid     = required_param('cmid',     PARAM_INT);
$groupid  = required_param('groupid',  PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$amount   = required_param('amount',   PARAM_INT);

if ($amount <= 0) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]),
        'Invalid amount.', null, \core\output\notification::NOTIFY_ERROR);
}

// Verify records exist.
$DB->get_record('course',         ['id' => $courseid], '*', MUST_EXIST);
$DB->get_record('groups',         ['id' => $groupid],  '*', MUST_EXIST);
$DB->get_record('course_modules', ['id' => $cmid],     '*', MUST_EXIST);

// Already purchased?
if ($DB->record_exists('groups_members', ['userid' => $USER->id, 'groupid' => $groupid])) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]),
        'لديك بالفعل صلاحية الوصول. You already have access.');
}

// Unique order ID encodes the intent so the callback can act on it.
// Format: vid-{userid}-{courseid}-{groupid}-{cmid}-{timestamp}
$order_id = 'vid-' . $USER->id . '-' . $courseid . '-' . $groupid . '-' . $cmid . '-' . time();

// Carry our order id in the redirect URL so the callback can always identify
// the purchase, no matter what params Kashier appends.
$redirect_url = (new moodle_url('/kashier/callback.php', ['k_order' => $order_id]))->out(false);
$webhook_url  = (new moodle_url('/kashier/webhook.php'))->out(false);
$description  = 'Video Purchase | Course ' . $courseid . ' | CM ' . $cmid;

// Store pending purchase in session (backup if order_id parsing fails).
$SESSION->kashier_pending_video = [
    'order_id' => $order_id,
    'userid'   => $USER->id,
    'courseid' => $courseid,
    'groupid'  => $groupid,
    'cmid'     => $cmid,
    'amount'   => $amount,
];

// Create a Kashier Payment Session (server-to-server) and hand the student the
// hosted checkout URL. Verification happens in callback.php / webhook.php via
// the session GET API — no client-side hash.
try {
    $session = kashier_create_session(
        $order_id,
        (float)$amount,
        $redirect_url,
        $webhook_url,
        $description
    );
    // Remember the session id so the callback can verify + grant access even if
    // Kashier's redirect omits the order/session query params.
    $SESSION->kashier_pending_video['sessionId'] = $session['sessionId'];
    // Persist a pending row keyed by order id → the callback recovers the
    // session id from the DB regardless of redirect params or session cookies.
    kashier_store_pending($order_id, $session['sessionId'], (int)$USER->id, (float)$amount, 'video');
    redirect($session['sessionUrl']);
} catch (\Exception $e) {
    error_log('kashier/pay.php session error: ' . $e->getMessage());
    // Show the raw gateway error to site admins so they can diagnose; students
    // just see the friendly bilingual message.
    $msg = 'خطأ في الاتصال ببوابة الدفع. حاول مرة أخرى. Payment gateway error, please try again.';
    if (is_siteadmin()) {
        $msg .= ' [admin] ' . s($e->getMessage());
    }
    redirect(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        $msg,
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}
