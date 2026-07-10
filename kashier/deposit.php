<?php
/**
 * Kashier — wallet top-up (deposit).
 *
 * GET params:
 *   amount (int) – amount in EGP to deposit
 *
 * Flow:
 *   User enters amount on wallet page → /kashier/deposit.php → Kashier checkout
 *   → /kashier/callback.php (dep-...) → wallet recharged → redirect to /e-wallet/
 */

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/config.php');

global $CFG, $USER, $SESSION;

if (!isloggedin() || isguestuser()) {
    redirect(get_login_url());
}

$amount = required_param('amount', PARAM_INT);

if ($amount <= 0) {
    redirect(new moodle_url('/e-wallet/'),
        'أدخل مبلغاً صحيحاً. Please enter a valid amount.',
        null, \core\output\notification::NOTIFY_ERROR);
}

// Order ID: dep-{userid}-{amount}-{timestamp}
$order_id = 'dep-' . $USER->id . '-' . $amount . '-' . time();

$redirect_url = $CFG->wwwroot . '/kashier/callback.php';
$webhook_url  = $CFG->wwwroot . '/kashier/webhook.php';
$description  = 'Wallet Deposit — ' . $amount . ' EGP';

$checkout_url = kashier_checkout_url(
    $order_id,
    (float)$amount,
    $redirect_url,
    $webhook_url,
    $description
);

// Session backup.
$SESSION->kashier_pending_deposit = [
    'order_id' => $order_id,
    'userid'   => $USER->id,
    'amount'   => $amount,
];

header('Location: ' . $checkout_url);
exit;
