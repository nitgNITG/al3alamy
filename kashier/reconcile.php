<?php
/**
 * Kashier reconciliation — the reliable delivery mechanism.
 *
 * Kashier's browser redirect (callback.php) and server webhook (webhook.php) are
 * best-effort: the redirect can be lost if the student closes the tab, and the
 * webhook is not always delivered. This script is the source of truth — it polls
 * every pending order, asks Kashier directly whether it was paid, and grants
 * access via the shared idempotent kashier_fulfill_order().
 *
 * Run from CLI or cron:
 *   php kashier/reconcile.php                 # reconcile recent pending orders
 *   php kashier/reconcile.php --order=vid-... # reconcile one specific order
 *   php kashier/reconcile.php --all           # ignore the age window
 *   php kashier/reconcile.php --verbose       # print every order examined
 *
 * Cron (every 5 minutes):
 *   [slash]5 * * * * /usr/bin/php /path/to/public_html/kashier/reconcile.php >> /var/log/kashier_reconcile.log 2>&1
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/config.php');

global $DB, $CFG;

// ── Options ──────────────────────────────────────────────────────────────────
$opts = getopt('', ['order:', 'all', 'verbose', 'max-age:']);
$one_order = isset($opts['order']) ? (string)$opts['order'] : '';
$all       = array_key_exists('all', $opts);
$verbose   = array_key_exists('verbose', $opts);
// Only look back this far by default, so we don't re-hammer ancient abandoned
// carts on every cron tick. 3 days covers any realistic redirect/webhook gap.
$max_age   = isset($opts['max-age']) ? (int)$opts['max-age'] : 3 * DAYSECS;

function reclog(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] kashier/reconcile: ' . $msg;
    error_log($line);
    echo $line . "\n";
}

// ── Gather pending orders ────────────────────────────────────────────────────
if ($one_order !== '') {
    $pending = $DB->get_records('kashier_transactions', ['order_id' => $one_order]);
} else {
    $params = ['status' => 'pending'];
    $where  = 'status = :status';
    if (!$all) {
        $where .= ' AND timecreated >= :since';
        $params['since'] = time() - $max_age;
    }
    $pending = $DB->get_records_select('kashier_transactions', $where, $params, 'timecreated ASC');
}

if (!$pending) {
    reclog('no pending orders to reconcile.');
    exit(0);
}

reclog('examining ' . count($pending) . ' pending order(s).');

$granted = 0;
$skipped = 0;
$failed  = 0;

foreach ($pending as $row) {
    $order_id   = $row->order_id;
    $session_id = (string)$row->transaction_id; // sessionId is parked here until paid.

    if ($session_id === '') {
        if ($verbose) { reclog("$order_id — no session id parked; cannot verify, skipping."); }
        $skipped++;
        continue;
    }

    $account = kashier_account_for_order($order_id);
    $verified = kashier_verify_session($session_id, $account);

    if (!$verified) {
        reclog("$order_id — verify returned empty response; will retry next run.");
        $failed++;
        continue;
    }

    if (!kashier_session_is_paid($verified)) {
        if ($verbose) {
            $st = strtoupper((string)($verified['status'] ?? '?'));
            reclog("$order_id — not paid yet (status=$st); leaving pending.");
        }
        $skipped++;
        continue;
    }

    // Paid — resolve the real amount + transaction id from Kashier, then fulfil.
    $amount = (float)($verified['capturedAmount'] ?? $verified['amount'] ?? $row->amount);
    if ($amount <= 0) { $amount = (float)$row->amount; }
    $txn_id = (string)($verified['transactionId']
        ?? $verified['orderId']
        ?? ($verified['history'][0]['transactionId'] ?? '')
        ?? $session_id);

    try {
        $res = kashier_fulfill_order($order_id, $txn_id, $amount);
    } catch (\Throwable $e) {
        reclog("$order_id — fulfilment error: " . $e->getMessage());
        $failed++;
        continue;
    }

    if (!$res['valid']) {
        reclog("$order_id — paid but order reference is malformed; needs manual review.");
        $failed++;
        continue;
    }
    if ($res['already']) {
        if ($verbose) { reclog("$order_id — already fulfilled; nothing to do."); }
        $skipped++;
        continue;
    }
    if ($res['done']) {
        reclog(sprintf(
            "$order_id — GRANTED (%s) user=%d amount=%.2f txn=%s",
            $res['type'], $res['userid'], $amount, $txn_id
        ));
        $granted++;
    }
}

reclog("done. granted=$granted skipped=$skipped failed=$failed");
exit($failed > 0 ? 1 : 0);
