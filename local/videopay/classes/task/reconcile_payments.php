<?php
namespace local_videopay\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task: reconcile pending Kashier orders and grant access.
 *
 * Runs on Moodle cron (every minute by default) so a student is enrolled and
 * added to the video's group within ~1 minute of paying, even when Kashier's
 * browser redirect or server webhook never reaches us.
 */
class reconcile_payments extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_reconcile_payments', 'local_videopay');
    }

    public function execute(): void {
        global $CFG;
        require_once($CFG->dirroot . '/kashier/config.php');

        $log = function (string $msg): void { mtrace($msg); };

        // Only chase orders from the last 3 days — old abandoned carts will
        // never be paid, so re-verifying them every minute is wasted API calls.
        $stats = kashier_reconcile(3 * DAYSECS, '', $log);

        mtrace(sprintf('kashier reconcile: examined=%d granted=%d skipped=%d failed=%d',
            $stats['examined'], $stats['granted'], $stats['skipped'], $stats['failed']));
    }
}
