<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../../config.php');

defined('MOODLE_INTERNAL') || die();

require_login();
$context = context_system::instance();
require_capability('local/subscriptions:manage', $context);

admin_externalpage_setup('local_subscriptions_admin');

use local_subscriptions\manager;

// ---- Handle POST actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $action = required_param('action', PARAM_ALPHA);
    $planid = required_param('planid', PARAM_INT);

    switch ($action) {
        case 'delete':
            try {
                manager::delete_plan($planid);
                redirect(
                    new moodle_url('/local/subscriptions/admin/plans.php'),
                    get_string('delete_plan', 'local_subscriptions') . ' ✓',
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            } catch (\moodle_exception $e) {
                redirect(
                    new moodle_url('/local/subscriptions/admin/plans.php'),
                    get_string('cannot_delete_has_subscribers', 'local_subscriptions'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }
            break;

        case 'toggle_status':
            $plan = manager::get_plan($planid);
            if ($plan) {
                if ($plan->status === manager::STATUS_ACTIVE) {
                    manager::deactivate_plan($planid);
                } else {
                    manager::activate_plan($planid);
                }
            }
            redirect(new moodle_url('/local/subscriptions/admin/plans.php'));
            break;
    }
}

// ---- Gather data ----
$plans     = manager::get_plans();
$stats_raw = manager::get_stats();

$total_plans    = count($plans);
$active_plans   = 0;
$inactive_plans = 0;
$total_subs     = $stats_raw['total'];

foreach ($plans as $p) {
    if ($p->status === manager::STATUS_ACTIVE) {
        $active_plans++;
    } else {
        $inactive_plans++;
    }
}

// Subscriber counts per plan.
$sub_counts = [];
foreach ($plans as $p) {
    $sub_counts[$p->id] = $DB->count_records('local_subscriptions_users', ['planid' => $p->id]);
}

// ---- Output ----
$PAGE->set_title(get_string('manage_plans', 'local_subscriptions'));
$PAGE->set_heading(get_string('manage_plans', 'local_subscriptions'));

echo $OUTPUT->header();
?>
<style>
.subs-stats-bar {
    display: flex;
    gap: 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.subs-stat-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 14px 22px;
    text-align: center;
    min-width: 120px;
    flex: 1;
}
.subs-stat-card .num {
    font-size: 2em;
    font-weight: bold;
    color: #2d6a9f;
    display: block;
}
.subs-stat-card .lbl {
    font-size: 0.85em;
    color: #666;
    margin-top: 4px;
    display: block;
}
.subs-table { width: 100%; border-collapse: collapse; }
.subs-table th, .subs-table td {
    padding: 10px 14px;
    border: 1px solid #dee2e6;
    vertical-align: middle;
}
.subs-table thead th {
    background: #2d6a9f;
    color: #fff;
    font-weight: 600;
}
.subs-table tbody tr:nth-child(even) { background: #f8f9fa; }
.badge-active   { background:#28a745; color:#fff; padding:2px 9px; border-radius:12px; font-size:.82em; }
.badge-inactive { background:#6c757d; color:#fff; padding:2px 9px; border-radius:12px; font-size:.82em; }
.btn-sm { padding: 4px 12px; border-radius: 4px; font-size: .85em; cursor: pointer; text-decoration: none; display: inline-block; }
.btn-primary { background:#2d6a9f; color:#fff; border: none; }
.btn-warning { background:#ffc107; color:#212529; border: none; }
.btn-danger  { background:#dc3545; color:#fff; border: none; }
.btn-success { background:#28a745; color:#fff; border: none; }
.top-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:10px; }
</style>

<div class="subs-stats-bar">
    <div class="subs-stat-card">
        <span class="num"><?php echo $total_plans; ?></span>
        <span class="lbl">إجمالي الخطط</span>
    </div>
    <div class="subs-stat-card">
        <span class="num" style="color:#28a745"><?php echo $active_plans; ?></span>
        <span class="lbl">خطط نشطة</span>
    </div>
    <div class="subs-stat-card">
        <span class="num" style="color:#6c757d"><?php echo $inactive_plans; ?></span>
        <span class="lbl">خطط معطلة</span>
    </div>
    <div class="subs-stat-card">
        <span class="num" style="color:#c8a84b"><?php echo $stats_raw['active']; ?></span>
        <span class="lbl">اشتراك فعال</span>
    </div>
    <div class="subs-stat-card">
        <span class="num"><?php echo $total_subs; ?></span>
        <span class="lbl">إجمالي الاشتراكات</span>
    </div>
    <div class="subs-stat-card">
        <span class="num" style="color:#17a2b8"><?php echo number_format($stats_raw['total_amount'], 2); ?> ج</span>
        <span class="lbl">إجمالي المبيعات</span>
    </div>
</div>

<div class="top-bar">
    <h3 style="margin:0"><?php echo get_string('plans_list', 'local_subscriptions'); ?></h3>
    <a href="<?php echo (new moodle_url('/local/subscriptions/admin/plan_edit.php'))->out(); ?>"
       class="btn-sm btn-primary">
        + <?php echo get_string('create_plan', 'local_subscriptions'); ?>
    </a>
</div>

<?php if (empty($plans)): ?>
    <p class="alert alert-info"><?php echo get_string('no_plans', 'local_subscriptions'); ?></p>
<?php else: ?>
<table class="subs-table">
    <thead>
        <tr>
            <th>#</th>
            <th><?php echo get_string('plan_name', 'local_subscriptions'); ?></th>
            <th><?php echo get_string('plan_price', 'local_subscriptions'); ?></th>
            <th><?php echo get_string('plan_status', 'local_subscriptions'); ?></th>
            <th><?php echo get_string('expiry_type', 'local_subscriptions'); ?></th>
            <th><?php echo get_string('subscribers_count', 'local_subscriptions'); ?></th>
            <th>الإجراءات</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($plans as $plan): ?>
    <tr>
        <td><?php echo $plan->id; ?></td>
        <td><strong><?php echo s($plan->name); ?></strong>
            <?php if ($plan->description): ?>
                <br><small style="color:#666"><?php echo s(shorten_text($plan->description, 60)); ?></small>
            <?php endif; ?>
        </td>
        <td><?php echo number_format((float)$plan->price, 2); ?> ج.م</td>
        <td>
            <?php if ($plan->status === manager::STATUS_ACTIVE): ?>
                <span class="badge-active"><?php echo get_string('plan_status_active', 'local_subscriptions'); ?></span>
            <?php else: ?>
                <span class="badge-inactive"><?php echo get_string('plan_status_inactive', 'local_subscriptions'); ?></span>
            <?php endif; ?>
        </td>
        <td>
            <?php if ($plan->expiry_type === manager::EXPIRY_DAYS): ?>
                <?php echo (int)$plan->expiry_days; ?> يوم
            <?php else: ?>
                <?php echo $plan->expiry_date ? userdate($plan->expiry_date, get_string('strftimedate', 'langconfig')) : '-'; ?>
            <?php endif; ?>
        </td>
        <td><?php echo (int)($sub_counts[$plan->id] ?? 0); ?></td>
        <td style="white-space:nowrap">
            <a href="<?php echo (new moodle_url('/local/subscriptions/admin/plan_edit.php', ['id' => $plan->id]))->out(); ?>"
               class="btn-sm btn-warning">
                <?php echo get_string('edit_plan', 'local_subscriptions'); ?>
            </a>

            <!-- Toggle active/inactive -->
            <form method="post" style="display:inline"
                  onsubmit="return confirm('<?php echo s(get_string('confirm_deactivate', 'local_subscriptions')); ?>')">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="planid" value="<?php echo $plan->id; ?>">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <?php if ($plan->status === manager::STATUS_ACTIVE): ?>
                    <button type="submit" class="btn-sm btn-warning">
                        <?php echo get_string('deactivate_plan', 'local_subscriptions'); ?>
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn-sm btn-success" onclick="this.form.onsubmit=null">
                        تفعيل
                    </button>
                <?php endif; ?>
            </form>

            <!-- Delete -->
            <form method="post" style="display:inline"
                  onsubmit="return confirm('<?php echo s(get_string('confirm_delete', 'local_subscriptions')); ?>')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="planid" value="<?php echo $plan->id; ?>">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <button type="submit" class="btn-sm btn-danger">
                    <?php echo get_string('delete_plan', 'local_subscriptions'); ?>
                </button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php echo $OUTPUT->footer(); ?>
