<?php
/**
 * Kashier reconciliation — CLI entry point.
 *
 * A thin wrapper over kashier_reconcile() (see kashier/config.php) for manual /
 * cron use. The same logic runs automatically as a Moodle scheduled task
 * (local_videopay\task\reconcile_payments), so a system crontab entry is only
 * needed if Moodle cron is not configured.
 *
 *   php kashier/reconcile.php                 # recent pending orders (last 3 days)
 *   php kashier/reconcile.php --order=vid-... # one specific order
 *   php kashier/reconcile.php --all           # ignore the age window
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/config.php');

$opts      = getopt('', ['order:', 'all', 'max-age:']);
$one_order = isset($opts['order']) ? (string)$opts['order'] : '';
$all       = array_key_exists('all', $opts);
$max_age   = $all ? 0 : (isset($opts['max-age']) ? (int)$opts['max-age'] : 3 * DAYSECS);

$log = function (string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] kashier/reconcile: ' . $msg . "\n";
};

$stats = kashier_reconcile($max_age, $one_order, $log);
$log(sprintf('done. examined=%d granted=%d skipped=%d failed=%d',
    $stats['examined'], $stats['granted'], $stats['skipped'], $stats['failed']));

exit($stats['failed'] > 0 ? 1 : 0);
