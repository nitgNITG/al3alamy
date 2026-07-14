<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin: detailed report for a single user subscription (US-AD-2-3):
 * snapshot vs current plan data, plan change history, and usage.
 *
 * @package    local_subscriptions
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

defined('MOODLE_INTERNAL') || die();

require_login();
$context = context_system::instance();
require_capability('local/subscriptions:viewreports', $context);

admin_externalpage_setup('local_subscriptions_report');

use local_subscriptions\manager;

$subid = required_param('subid', PARAM_INT);

$sub = $DB->get_record_sql(
    "SELECT s.*, u.firstname, u.lastname, u.email
       FROM {local_subscriptions_users} s
       JOIN {user} u ON u.id = s.userid
      WHERE s.id = :id", ['id' => $subid], MUST_EXIST);

$current_plan = manager::get_plan((int)$sub->planid);
$snapshot     = !empty($sub->snapshot) ? json_decode($sub->snapshot, true) : [];
$history      = $DB->get_records('local_subscriptions_history', ['planid' => $sub->planid], 'timecreated ASC');

// Helper to resolve a course name.
$course_name = function($cid) use ($DB) {
    $c = $DB->get_record('course', ['id' => $cid], 'fullname', IGNORE_MISSING);
    return $c ? $c->fullname : ('#' . $cid);
};

// Usage: unlocked lessons for this subscription (credit plans).
$unlocks = $DB->get_records('local_subscriptions_unlocks', ['subscriptionid' => $subid], 'timecreated ASC');

// Admin names.
$admin_name = function($uid) use ($DB) {
    if (!$uid) { return '-'; }
    $u = $DB->get_record('user', ['id' => $uid], 'id, firstname, lastname', IGNORE_MISSING);
    return $u ? trim($u->firstname . ' ' . $u->lastname) : ('#' . $uid);
};

$PAGE->set_title(get_string('report_detail_title', 'local_subscriptions'));
$PAGE->set_heading(get_string('report_detail_title', 'local_subscriptions'));

echo $OUTPUT->header();
?>
<style>
.rd-wrap { max-width: 900px; margin: 0 auto; direction: rtl; }
.rd-box { background:#fff; border:1px solid #dee2e6; border-radius:10px; padding:20px; margin-bottom:18px; }
.rd-box h3 { margin:0 0 12px; color:#2d6a9f; font-size:1.05em; border-bottom:2px solid #eef2f7; padding-bottom:6px; }
.rd-row { display:flex; justify-content:space-between; padding:5px 0; border-bottom:1px solid #f3f5f8; font-size:.92em; }
.rd-row:last-child { border-bottom:none; }
.rd-row .k { color:#666; }
.rd-row .v { font-weight:600; }
.rd-two { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
.rd-table { width:100%; border-collapse:collapse; font-size:.86em; }
.rd-table th, .rd-table td { border:1px solid #e5e9ef; padding:6px 10px; text-align:right; }
.rd-table th { background:#f0f4fa; color:#2d6a9f; }
.rd-chip { display:inline-block; background:#eef3f9; border-radius:14px; padding:3px 12px; margin:3px; font-size:.85em; }
@media (max-width:700px){ .rd-two { grid-template-columns:1fr; } }
</style>

<div class="rd-wrap">

  <!-- Subscriber -->
  <div class="rd-box">
    <h3><?php echo get_string('report_subscriber', 'local_subscriptions'); ?></h3>
    <div class="rd-row"><span class="k"><?php echo get_string('assign_user', 'local_subscriptions'); ?></span>
        <span class="v"><?php echo s($sub->firstname . ' ' . $sub->lastname); ?> (<?php echo s($sub->email); ?>)</span></div>
    <div class="rd-row"><span class="k"><?php echo get_string('plan_status', 'local_subscriptions'); ?></span>
        <span class="v"><?php echo s($sub->status); ?></span></div>
    <div class="rd-row"><span class="k"><?php echo get_string('payment_method', 'local_subscriptions'); ?></span>
        <span class="v"><?php echo $sub->source === 'online' ? get_string('pay_method_online', 'local_subscriptions') : get_string('pay_method_offline', 'local_subscriptions'); ?></span></div>
    <div class="rd-row"><span class="k"><?php echo get_string('start_date', 'local_subscriptions'); ?></span>
        <span class="v"><?php echo $sub->start_time ? userdate($sub->start_time) : '-'; ?></span></div>
    <div class="rd-row"><span class="k"><?php echo get_string('expiry_date_field', 'local_subscriptions'); ?></span>
        <span class="v"><?php echo $sub->expiry_time ? userdate($sub->expiry_time) : '-'; ?></span></div>
    <div class="rd-row"><span class="k"><?php echo get_string('amount_paid', 'local_subscriptions'); ?></span>
        <span class="v"><?php echo number_format((float)$sub->amount_paid, 2); ?> ج</span></div>
    <?php if ($sub->assigned_by): ?>
    <div class="rd-row"><span class="k"><?php echo get_string('report_assigned_by', 'local_subscriptions'); ?></span>
        <span class="v"><?php echo s($admin_name($sub->assigned_by)); ?></span></div>
    <?php endif; ?>
    <?php if ($sub->status === 'cancelled'): ?>
    <div class="rd-row"><span class="k"><?php echo get_string('report_cancelled_by', 'local_subscriptions'); ?></span>
        <span class="v"><?php echo s($admin_name($sub->cancelled_by)); ?> — <?php echo $sub->cancelled_time ? userdate($sub->cancelled_time) : ''; ?></span></div>
    <div class="rd-row"><span class="k"><?php echo get_string('unsubscribe_reason', 'local_subscriptions'); ?></span>
        <span class="v"><?php echo s($sub->cancel_reason ?: '-'); ?></span></div>
    <div class="rd-row"><span class="k"><?php echo get_string('refund_amount', 'local_subscriptions'); ?></span>
        <span class="v"><?php echo $sub->refund_amount ? number_format((float)$sub->refund_amount, 2) . ' ج' : get_string('refund_not_returned', 'local_subscriptions'); ?></span></div>
    <?php endif; ?>
  </div>

  <!-- Snapshot vs Current -->
  <div class="rd-two">
    <div class="rd-box">
      <h3><?php echo get_string('report_snapshot', 'local_subscriptions'); ?></h3>
      <?php if ($snapshot): ?>
        <div class="rd-row"><span class="k"><?php echo get_string('plan_name', 'local_subscriptions'); ?></span>
            <span class="v"><?php echo s($snapshot['name'] ?? '-'); ?></span></div>
        <div class="rd-row"><span class="k"><?php echo get_string('plan_price', 'local_subscriptions'); ?></span>
            <span class="v"><?php echo isset($snapshot['price']) ? number_format((float)$snapshot['price'], 2) . ' ج' : '-'; ?></span></div>
        <div class="rd-row"><span class="k"><?php echo get_string('unlock_limit', 'local_subscriptions'); ?></span>
            <span class="v"><?php echo isset($snapshot['unlock_limit']) ? (int)$snapshot['unlock_limit'] : 0; ?></span></div>
        <div style="margin-top:8px">
          <strong><?php echo get_string('courses_section', 'local_subscriptions'); ?>:</strong><br>
          <?php if (!empty($snapshot['items'])): foreach ($snapshot['items'] as $it): ?>
            <span class="rd-chip"><?php echo s($course_name($it['courseid'] ?? 0)); ?> · <?php echo s($it['lesson_access_type'] ?? 'all'); ?></span>
          <?php endforeach; else: ?>
            <span style="color:#888"><?php echo get_string('course_access_all', 'local_subscriptions'); ?></span>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <p style="color:#888"><?php echo get_string('report_no_snapshot', 'local_subscriptions'); ?></p>
      <?php endif; ?>
    </div>

    <div class="rd-box">
      <h3><?php echo get_string('report_current', 'local_subscriptions'); ?></h3>
      <?php if ($current_plan): ?>
        <div class="rd-row"><span class="k"><?php echo get_string('plan_name', 'local_subscriptions'); ?></span>
            <span class="v"><?php echo s($current_plan->name); ?></span></div>
        <div class="rd-row"><span class="k"><?php echo get_string('plan_price', 'local_subscriptions'); ?></span>
            <span class="v"><?php echo number_format((float)$current_plan->price, 2); ?> ج</span></div>
        <div class="rd-row"><span class="k"><?php echo get_string('unlock_limit', 'local_subscriptions'); ?></span>
            <span class="v"><?php echo (int)$current_plan->unlock_limit; ?></span></div>
        <div class="rd-row"><span class="k"><?php echo get_string('plan_status', 'local_subscriptions'); ?></span>
            <span class="v"><?php echo s($current_plan->status); ?></span></div>
        <div style="margin-top:8px">
          <strong><?php echo get_string('courses_section', 'local_subscriptions'); ?>:</strong><br>
          <?php foreach (manager::get_plan_items((int)$current_plan->id) as $it): ?>
            <span class="rd-chip"><?php echo s($course_name($it->courseid)); ?> · <?php echo s($it->lesson_access_type); ?></span>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p style="color:#888"><?php echo get_string('report_plan_deleted', 'local_subscriptions'); ?></p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Usage -->
  <div class="rd-box">
    <h3><?php echo get_string('report_usage', 'local_subscriptions'); ?></h3>
    <div class="rd-row"><span class="k"><?php echo get_string('unlocked_lessons', 'local_subscriptions'); ?></span>
        <span class="v"><?php echo count($unlocks); ?></span></div>
    <?php if ($unlocks): ?>
    <div style="margin-top:8px">
      <?php foreach ($unlocks as $u): ?>
        <?php
          $cm = $DB->get_record('course_modules', ['id' => $u->cmid], 'id, module, instance', IGNORE_MISSING);
          $nm = '#' . $u->cmid;
          if ($cm) { $mt = $DB->get_field('modules', 'name', ['id' => $cm->module]);
                     if ($mt) { $mn = $DB->get_field($mt, 'name', ['id' => $cm->instance], IGNORE_MISSING); if ($mn) { $nm = $mn; } } }
        ?>
        <span class="rd-chip">🔓 <?php echo s($nm); ?> · <?php echo userdate($u->timecreated, '%d/%m/%Y'); ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Change history -->
  <div class="rd-box">
    <h3><?php echo get_string('report_change_history', 'local_subscriptions'); ?></h3>
    <?php if ($history): ?>
    <table class="rd-table">
      <thead><tr>
        <th><?php echo get_string('report_change_when', 'local_subscriptions'); ?></th>
        <th><?php echo get_string('report_change_type', 'local_subscriptions'); ?></th>
        <th><?php echo get_string('report_change_field', 'local_subscriptions'); ?></th>
        <th><?php echo get_string('report_change_old', 'local_subscriptions'); ?></th>
        <th><?php echo get_string('report_change_new', 'local_subscriptions'); ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($history as $h): ?>
        <tr>
          <td><?php echo userdate($h->timecreated, '%d/%m/%Y %H:%M'); ?></td>
          <td><?php echo s($h->change_type); ?></td>
          <td><?php echo s($h->field_name ?: '-'); ?></td>
          <td><?php echo s(shorten_text((string)$h->old_value, 40)); ?></td>
          <td><?php echo s(shorten_text((string)$h->new_value, 40)); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <p style="color:#888"><?php echo get_string('report_no_changes', 'local_subscriptions'); ?></p>
    <?php endif; ?>
  </div>

  <p><a href="<?php echo (new moodle_url('/local/subscriptions/admin/report.php'))->out(); ?>"
        style="color:#2d6a9f;text-decoration:none">&larr; <?php echo get_string('reports', 'local_subscriptions'); ?></a></p>

</div>
<?php echo $OUTPUT->footer();
