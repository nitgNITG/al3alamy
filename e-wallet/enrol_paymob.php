<?php
/**
 * Direct Paymob payment for course enrollment.
 *
 * Flow:
 *   User clicks "Pay with Card" on locked module popup
 *   → this page creates a Paymob payment intention
 *   → user pays on Paymob checkout
 *   → Paymob redirects to paymob_response.php
 *   → paymob_response.php detects enrollment payment → enrolls user + adds to group
 */

require_once('vendor/autoload.php');
require_once(__DIR__ . '/../config.php');

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

global $DB, $CFG, $USER, $SESSION;

// Must be logged in.
if (!isloggedin() || isguestuser()) {
    redirect(get_login_url());
}

// Required params.
$courseid = required_param('courseid', PARAM_INT);
$groupid  = required_param('groupid',  PARAM_INT);
$amount   = required_param('amount',   PARAM_INT);

// Validate amount.
if ($amount <= 0) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]),
        'Invalid payment amount.', null, \core\output\notification::NOTIFY_ERROR);
}

// Validate course and group exist.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$group  = $DB->get_record('groups',  ['id' => $groupid],  '*', MUST_EXIST);

// Guard: already enrolled.
$course_context = context_course::instance($courseid);
if (is_enrolled($course_context, $USER)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]),
        'You are already enrolled in this course.');
}

// Guard: already in group.
if ($DB->record_exists('groups_members', ['userid' => $USER->id, 'groupid' => $groupid])) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]),
        'You already have access to this content.');
}

// ── Build Paymob payment intention ────────────────────────────────────────
$Secret_Key = 'egy_sk_live_8cdd0d41ce2648eaa40cbdbe95fde334f3a97de420a5d302edc14c6fb2100c5e';
$publicKey  = 'egy_pk_live_qfif3UUBZBPCrzHN27ZeimbWTRPoWlrZ';

// type param allows choosing card or e-wallet integration
$type = optional_param('type', 'card', PARAM_ALPHA);
$IntegrationID = ($type === 'wallet') ? 4830246 : 4632692; // e-wallet : card

$amountInCents = $amount * 100;

// Encode enrollment intent in merchant_order_id so the callback can parse it
$merchant_order_id = 'enrol-' . $USER->id . '-' . $courseid . '-' . $groupid;

$headers = [
    'Authorization' => 'Token ' . $Secret_Key,
    'Content-Type'  => 'application/json',
];

$body = json_encode([
    'amount'             => $amountInCents,
    'currency'           => 'EGP',
    'payment_methods'    => [1, $IntegrationID],
    'merchant_order_id'  => $merchant_order_id,
    'special_reference'  => $merchant_order_id,
    'items'              => [[
        'name'        => 'Course Enrollment',
        'amount'      => $amountInCents,
        'description' => 'Enrollment: ' . $course->fullname . ' | Group: ' . $group->name,
        'quantity'    => 1,
    ]],
    'billing_data' => [
        'apartment'    => 'NA',
        'first_name'   => $USER->firstname,
        'last_name'    => ($USER->lastname ?: '.'),
        'street'       => 'NA',
        'building'     => 'NA',
        'phone_number' => ($USER->phone1 ?: '01000000000'),
        'country'      => 'EGY',
        'email'        => $USER->email,
        'floor'        => 'NA',
        'state'        => 'Cairo',
    ],
    'customer' => [
        'first_name' => $USER->firstname,
        'last_name'  => ($USER->lastname ?: '.'),
        'email'      => $USER->email,
    ],
    'extras' => [
        'type'     => 'enrol',
        'userid'   => $USER->id,
        'courseid' => $courseid,
        'groupid'  => $groupid,
    ],
]);

try {
    $client = new Client();
    $req    = new Request('POST', 'https://accept.paymob.com/v1/intention/', $headers, $body);
    $res    = $client->sendAsync($req)->wait();
    $data   = json_decode($res->getBody()->getContents(), true);

    if ($data && isset($data['client_secret'])) {
        // Store enrollment intent in session as a safety backup
        $SESSION->paymob_enrol_pending = [
            'merchant_order_id' => $merchant_order_id,
            'userid'            => $USER->id,
            'courseid'          => $courseid,
            'groupid'           => $groupid,
            'amount'            => $amount,
        ];

        header('Location: https://accept.paymob.com/unifiedcheckout/?publicKey='
            . $publicKey . '&clientSecret=' . $data['client_secret']);
        exit;
    } else {
        error_log('enrol_paymob.php: Paymob intention failed — ' . json_encode($data));
        redirect(new moodle_url('/course/view.php', ['id' => $courseid]),
            'Payment gateway error. Please try again.',
            null, \core\output\notification::NOTIFY_ERROR);
    }
} catch (Exception $e) {
    error_log('enrol_paymob.php exception: ' . $e->getMessage());
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]),
        'Payment error: ' . $e->getMessage(),
        null, \core\output\notification::NOTIFY_ERROR);
}
