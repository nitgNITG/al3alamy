<?php
/**
 * kashier/diagnose.php — CLI diagnostic for Payment Sessions.
 *
 * Runs a throwaway create-session call for the STUDENT account and prints the
 * two diagnostic log lines (request + raw response) straight to the console,
 * so you don't have to hunt through the PHP error log.
 *
 *   Usage on the server:  php kashier/diagnose.php
 *
 * SAFE: uses a dummy order id, amount 1.00, and does NOT store anything.
 * Delete this file once payments are confirmed working.
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once(__DIR__ . '/config.php');

$type  = 'student';
$creds = kashier_account($type);

fwrite(STDOUT, "== Kashier session diagnostic ($type) ==\n");
fwrite(STDOUT, 'merchant_id : ' . ($creds['merchant_id'] !== '' ? $creds['merchant_id'] : 'MISSING') . "\n");
fwrite(STDOUT, 'api_key len : ' . strlen($creds['api_key']) . "\n");
fwrite(STDOUT, 'secret len  : ' . strlen($creds['secret_key']) . "\n");
fwrite(STDOUT, "----\n");

$order_id = 'diag-' . time();
try {
    $session = kashier_create_session(
        $order_id,
        1.00,
        (new moodle_url('/kashier/callback.php'))->out(false),
        (new moodle_url('/kashier/webhook.php'))->out(false),
        'diagnostic'
    );
    fwrite(STDOUT, "SUCCESS — sessionUrl: {$session['sessionUrl']}\n");
    fwrite(STDOUT, "sessionId: {$session['sessionId']}\n");
} catch (\Exception $e) {
    fwrite(STDOUT, 'FAILED — ' . $e->getMessage() . "\n");
}
fwrite(STDOUT, "(see the two error_log lines above for full request/response)\n");
