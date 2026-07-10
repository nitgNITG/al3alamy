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

$redirect_url = $CFG->wwwroot . '/kashier/callback.php';
$webhook_url  = $CFG->wwwroot . '/kashier/webhook.php';
$description  = 'Video Purchase | Course ' . $courseid . ' | CM ' . $cmid;

$checkout_url = kashier_checkout_url(
    $order_id,
    (float)$amount,
    $redirect_url,
    $webhook_url,
    $description
);

// Store pending purchase in session (backup if order_id parsing fails).
$SESSION->kashier_pending_video = [
    'order_id' => $order_id,
    'userid'   => $USER->id,
    'courseid' => $courseid,
    'groupid'  => $groupid,
    'cmid'     => $cmid,
    'amount'   => $amount,
];

header('Location: ' . $checkout_url);
exit;
