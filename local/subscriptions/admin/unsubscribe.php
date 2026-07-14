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

/* Confirmation modal */
.us-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:100000; direction:rtl; }
.us-modal-overlay.open { display:flex; align-items:center; justify-content:center; }
.us-modal-box { background:#fff; width:100%; max-width:420px; margin:16px; border-radius:12px; box-shadow:0 12px 40px rgba(0,0,0,.25); overflow:hidden; animation:us-pop .18s ease-out; font-family:'Segoe UI',Tahoma,Arial,sans-serif; }
@keyframes us-pop { from { transform:scale(.94); opacity:0; } to { transform:scale(1); opacity:1; } }
.us-modal-head { display:flex; align-items:center; gap:12px; padding:20px 22px 0; }
.us-modal-icon { width:44px; height:44px; border-radius:50%; background:#fdecea; color:#dc3545; display:flex; align-items:center; justify-content:center; font-size:24px; flex:none; }
.us-modal-head h4 { margin:0; font-size:1.15em; color:#222; }
.us-modal-body { padding:12px 22px 4px; color:#555; font-size:.95em; line-height:1.6; }
.us-modal-body .sum { background:#f7f9fc; border:1px solid #e6edf5; border-radius:8px; padding:10px 14px; margin-top:12px; font-size:.9em; }
.us-modal-body .sum .r { display:flex; justify-content:space-between; padding:3px 0; }
.us-modal-body .sum .r .k { color:#777; }
.us-modal-body .sum .r .v { font-weight:700; color:#333; }
.us-modal-actions { display:flex; gap:10px; padding:18px 22px 22px; }
.us-modal-actions button { flex:1; padding:10px; border:none; border-radius:8px; font-size:1em; font-weight:600; cursor:pointer; }
.us-modal-actions .confirm { background:#dc3545; color:#fff; }
.us-modal-actions .confirm:hover { background:#c82333; }
.us-modal-actions .cancel { background:#eef0f2; color:#333; }
.us-modal-actions .cancel:hover { background:#e2e5e8; }
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

  <form method="post" id="us-unsub-form">
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

    <button type="button" id="us-open-confirm" class="btn btn-danger">
        <?php echo get_string('unsub_confirm', 'local_subscriptions'); ?>
    </button>
    <a href="<?php echo $reporturl->out(); ?>" class="btn btn-secondary"><?php echo get_string('cancel'); ?></a>
  </form>
</div>

<!-- Confirmation modal -->
<div class="us-modal-overlay" id="us-confirm-modal">
  <div class="us-modal-box" role="dialog" aria-modal="true" aria-labelledby="us-modal-title">
    <div class="us-modal-head">
      <div class="us-modal-icon">&#9888;</div>
      <h4 id="us-modal-title"><?php echo get_string('unsub_confirm', 'local_subscriptions'); ?></h4>
    </div>
    <div class="us-modal-body">
      <p style="margin:0"><?php echo s(get_string('unsub_confirm', 'local_subscriptions')); ?>&#1567;</p>
      <div class="sum">
        <div class="r"><span class="k"><?php echo get_string('assign_user', 'local_subscriptions'); ?></span>
            <span class="v"><?php echo s($sub->firstname . ' ' . $sub->lastname); ?></span></div>
        <div class="r"><span class="k"><?php echo get_string('plan_name', 'local_subscriptions'); ?></span>
            <span class="v"><?php echo s($sub->plan_name); ?></span></div>
        <div class="r"><span class="k"><?php echo get_string('refund_status', 'local_subscriptions'); ?></span>
            <span class="v" id="us-modal-refund"></span></div>
      </div>
    </div>
    <div class="us-modal-actions">
      <button type="submit" form="us-unsub-form" class="confirm"><?php echo get_string('unsub_confirm', 'local_subscriptions'); ?></button>
      <button type="button" class="cancel" id="us-modal-cancel"><?php echo get_string('cancel'); ?></button>
    </div>
  </div>
</div>

<script>
(function () {
  var form    = document.getElementById('us-unsub-form');
  var overlay = document.getElementById('us-confirm-modal');
  var openBtn = document.getElementById('us-open-confirm');
  var cancel  = document.getElementById('us-modal-cancel');
  var refEl   = document.getElementById('us-modal-refund');
  var L_RETURNED     = <?php echo json_encode(get_string('refund_returned', 'local_subscriptions')); ?>;
  var L_NOT_RETURNED = <?php echo json_encode(get_string('refund_not_returned', 'local_subscriptions')); ?>;

  function open() {
    // Run native validation first (reason is required).
    if (!form.reportValidity()) { return; }
    var st = form.querySelector('input[name="refund_status"]:checked');
    if (st && st.value === 'returned') {
      var amt = form.querySelector('input[name="refund_amount"]');
      refEl.textContent = L_RETURNED + ' (' + (amt ? Number(amt.value || 0).toFixed(2) : '0.00') + ' ج)';
    } else {
      refEl.textContent = L_NOT_RETURNED;
    }
    overlay.classList.add('open');
  }
  function close() { overlay.classList.remove('open'); }

  openBtn.addEventListener('click', open);
  cancel.addEventListener('click', close);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>
<?php echo $OUTPUT->footer();
