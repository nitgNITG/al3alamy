<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin: manually unsubscribe a user (US-AD-2-2). Server-rendered form — no JS.
 *
 * @package    local_subscriptions
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

defined('MOODLE_INTERNAL') || die();

require_login();
$context = context_system::instance();
require_capability('local/subscriptions:manage', $context);

admin_externalpage_setup('local_subscriptions_report');

use local_subscriptions\manager;

$subid   = required_param('subid', PARAM_INT);
$reporturl = new moodle_url('/local/subscriptions/admin/report.php');

$sub = $DB->get_record_sql(
    "SELECT s.*, p.name AS plan_name, u.firstname, u.lastname, u.email
       FROM {local_subscriptions_users} s
       JOIN {local_subscriptions_plans} p ON p.id = s.planid
       JOIN {user} u ON u.id = s.userid
      WHERE s.id = :id", ['id' => $subid], MUST_EXIST);

if ($sub->status !== manager::STATUS_ACTIVE) {
    redirect($reporturl, get_string('unsub_not_active', 'local_subscriptions'),
        null, \core\output\notification::NOTIFY_ERROR);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $refund_status = required_param('refund_status', PARAM_ALPHA); // returned | notreturned
    $reason        = trim(required_param('reason', PARAM_TEXT));
    $refund_amount = ($refund_status === 'returned')
        ? (float)optional_param('refund_amount', 0, PARAM_FLOAT) : 0.0;

    if ($reason === '') {
        $errors[] = get_string('unsub_reason_required', 'local_subscriptions');
    }
    if ($refund_amount < 0 || $refund_amount > (float)$sub->amount_paid) {
        $errors[] = get_string('unsub_refund_invalid', 'local_subscriptions');
    }

    if (empty($errors)) {
        manager::unsubscribe_user($subid, (int)$USER->id, $reason, $refund_amount);
        redirect($reporturl, get_string('unsub_success', 'local_subscriptions'),
            null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

$PAGE->set_title(get_string('unsubscribe_user', 'local_subscriptions'));
$PAGE->set_heading(get_string('unsubscribe_user', 'local_subscriptions'));

echo $OUTPUT->header();
?>
<style>
.us-page { max-width: 560px; margin: 0 auto; direction: rtl; }
.us-page .form-group { margin-bottom: 16px; }
.us-page label { font-weight: 600; display: block; margin-bottom: 5px; }
.us-page .form-control { width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; }
.us-page .radios label { font-weight: normal; display: inline-flex; align-items: center; gap: 6px; margin-inline-end: 18px; }
.us-info { background:#f0f7ff; border:1px solid #cfe2ff; border-radius:8px; padding:14px; margin-bottom:18px; }
.us-info .row { display:flex; justify-content:space-between; padding:4px 0; }
.us-info .k { color:#555; } .us-info .v { font-weight:700; }
.alert-error { background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:10px 16px; border-radius:4px; margin-bottom:16px; }
.alert-error ul { margin:0; padding-inline-start:20px; }
.btn { padding:9px 22px; border-radius:4px; font-size:1em; cursor:pointer; border:none; }
.btn-danger { background:#dc3545; color:#fff; }
.btn-secondary { background:#6c757d; color:#fff; text-decoration:none; display:inline-block; }
</style>

<div class="us-page">
  <?php if (!empty($errors)): ?>
  <div class="alert-error"><ul>
      <?php foreach ($errors as $e): ?><li><?php echo s($e); ?></li><?php endforeach; ?>
  </ul></div>
  <?php endif; ?>

  <div class="us-info">
    <div class="row"><span class="k"><?php echo get_string('assign_user', 'local_subscriptions'); ?></span>
        <span class="v"><?php echo s($sub->firstname . ' ' . $sub->lastname); ?> (<?php echo s($sub->email); ?>)</span></div>
    <div class="row"><span class="k"><?php echo get_string('plan_name', 'local_subscriptions'); ?></span>
        <span class="v"><?php echo s($sub->plan_name); ?></span></div>
    <div class="row"><span class="k"><?php echo get_string('amount_paid', 'local_subscriptions'); ?></span>
        <span class="v"><?php echo number_format((float)$sub->amount_paid, 2); ?> ج</span></div>
  </div>

  <form method="post">
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

    <div class="form-group">
        <label><?php echo get_string('refund_status', 'local_subscriptions'); ?></label>
        <div class="radios">
            <label><input type="radio" name="refund_status" value="notreturned" checked
                          onclick="document.getElementById('us-amount-wrap').style.display='none'">
                <?php echo get_string('refund_not_returned', 'local_subscriptions'); ?></label>
            <label><input type="radio" name="refund_status" value="returned"
                          onclick="document.getElementById('us-amount-wrap').style.display='block'">
                <?php echo get_string('refund_returned', 'local_subscriptions'); ?></label>
        </div>
    </div>

    <div class="form-group" id="us-amount-wrap" style="display:none">
        <label><?php echo get_string('refund_amount', 'local_subscriptions'); ?>
            (<?php echo get_string('unsub_max', 'local_subscriptions'); ?> <?php echo number_format((float)$sub->amount_paid, 2); ?> ج)</label>
        <input type="number" name="refund_amount" class="form-control" step="0.01" min="0"
               max="<?php echo (float)$sub->amount_paid; ?>" value="0" style="max-width:220px">
    </div>

    <div class="form-group">
        <label><?php echo get_string('unsubscribe_reason', 'local_subscriptions'); ?> *</label>
        <textarea name="reason" class="form-control" rows="3" required></textarea>
    </div>

    <button type="submit" class="btn btn-danger"
            onclick="return confirm('<?php echo s(get_string('unsub_confirm', 'local_subscriptions')); ?>?')">
        <?php echo get_string('unsub_confirm', 'local_subscriptions'); ?>
    </button>
    <a href="<?php echo $reporturl->out(); ?>" class="btn btn-secondary"><?php echo get_string('cancel'); ?></a>
  </form>
</div>
<?php echo $OUTPUT->footer();
