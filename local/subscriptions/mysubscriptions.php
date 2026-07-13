<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../config.php');

require_login();

use local_subscriptions\manager;

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/subscriptions/mysubscriptions.php'));
$PAGE->set_title(get_string('my_subscriptions', 'local_subscriptions'));
$PAGE->set_heading(get_string('my_subscriptions', 'local_subscriptions'));
$PAGE->set_pagelayout('standard');

// Get active subscription.
$active_sub  = manager::get_active_subscription($USER->id);
$all_subs    = manager::get_user_subscriptions($USER->id);
$active_plan = null;
$active_items = [];

if ($active_sub) {
    $active_plan  = manager::get_plan($active_sub->planid);
    $active_items = manager::get_plan_items($active_sub->planid);
}

// Get course names for active plan items.
$active_course_info = [];
if ($active_items) {
    $course_ids = array_unique(array_column($active_items, 'courseid'));
    foreach ($course_ids as $cid) {
        $c = $DB->get_record('course', ['id' => $cid], 'id, fullname, shortname', IGNORE_MISSING);
        if ($c) {
            $active_course_info[$cid] = $c;
        }
    }
}

// Credit-plan info: unlock limit, remaining, and the names of already-unlocked lessons.
$unlock_limit    = 0;
$unlock_remaining = 0;
$unlocked_names  = [];
if ($active_sub) {
    $unlock_limit = manager::get_unlock_limit_for($active_sub);
    if ($unlock_limit > 0) {
        $unlock_remaining = manager::get_remaining_unlocks($active_sub);
        $unlock_rows = $DB->get_records('local_subscriptions_unlocks',
            ['subscriptionid' => $active_sub->id], 'timecreated ASC');
        foreach ($unlock_rows as $ur) {
            $cm = $DB->get_record('course_modules', ['id' => $ur->cmid], 'id, module, instance', IGNORE_MISSING);
            $lname = 'درس #' . (int)$ur->cmid;
            if ($cm) {
                $modtype = $DB->get_field('modules', 'name', ['id' => $cm->module]);
                if ($modtype) {
                    $mn = $DB->get_field($modtype, 'name', ['id' => $cm->instance], IGNORE_MISSING);
                    if ($mn) {
                        $lname = $mn;
                    }
                }
            }
            $unlocked_names[] = $lname;
        }
    }
}

echo $OUTPUT->header();
?>
<style>
.mysubs-page { direction: rtl; font-family: 'Segoe UI', Tahoma, Arial, sans-serif; max-width: 900px; margin: 0 auto; padding: 20px; }
.mysubs-page h1 { color: #2d6a9f; font-size: 1.7em; margin-bottom: 6px; }

/* Active subscription card */
.active-sub-card {
    background: linear-gradient(135deg, #2d6a9f 0%, #1d5080 100%);
    color: #fff;
    border-radius: 14px;
    padding: 28px;
    margin-bottom: 30px;
    box-shadow: 0 4px 18px rgba(45,106,159,0.25);
}
.active-sub-card h2 { margin: 0 0 16px; font-size: 1.2em; opacity: .85; font-weight: 400; }
.active-sub-card .plan-title { font-size: 1.7em; font-weight: 800; margin-bottom: 16px; }
.sub-meta { display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 18px; }
.sub-meta-item { text-align: center; }
.sub-meta-item .val { font-size: 1.6em; font-weight: 700; display: block; }
.sub-meta-item .lbl { font-size: .78em; opacity: .8; margin-top: 2px; display: block; }
.days-badge {
    background: #c8a84b;
    color: #fff;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: .9em;
    font-weight: 700;
    display: inline-block;
    margin-bottom: 16px;
}
.courses-included { margin-top: 16px; }
.courses-included h3 { font-size: .95em; opacity: .8; font-weight: 400; margin-bottom: 10px; }
.course-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.course-chip { background: rgba(255,255,255,0.18); padding: 5px 14px; border-radius: 20px; font-size: .87em; }

/* No subscription */
.no-sub-card {
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 14px;
    padding: 40px;
    text-align: center;
    margin-bottom: 30px;
    color: #666;
}
.no-sub-card p { font-size: 1.05em; margin-bottom: 16px; }
.btn-browse {
    display: inline-block;
    background: #2d6a9f;
    color: #fff;
    padding: 11px 28px;
    border-radius: 8px;
    font-size: 1em;
    font-weight: 600;
    text-decoration: none;
    transition: background .2s;
}
.btn-browse:hover { background: #1d5080; color: #fff; }

/* History table */
.section-title { font-size: 1.1em; font-weight: 700; color: #2d6a9f; margin: 24px 0 12px; padding-bottom: 6px; border-bottom: 2px solid #2d6a9f; }
.history-table { width: 100%; border-collapse: collapse; font-size: .9em; }
.history-table th, .history-table td { padding: 10px 14px; border: 1px solid #dee2e6; vertical-align: middle; }
.history-table thead th { background: #f0f4fa; color: #2d6a9f; font-weight: 700; }
.history-table tbody tr:nth-child(even) { background: #fafafa; }
.badge-active   { background:#28a745; color:#fff; padding:2px 9px; border-radius:10px; font-size:.8em; }
.badge-expired  { background:#6c757d; color:#fff; padding:2px 9px; border-radius:10px; font-size:.8em; }
.badge-cancelled{ background:#dc3545; color:#fff; padding:2px 9px; border-radius:10px; font-size:.8em; }
</style>

<div class="mysubs-page">
    <h1><?php echo get_string('my_subscriptions', 'local_subscriptions'); ?></h1>
    <p style="color:#666; margin-bottom:24px">مرحباً <strong><?php echo s(fullname($USER)); ?></strong>، هنا تجد تفاصيل اشتراكاتك</p>

    <!-- Active Subscription -->
    <?php if ($active_sub && $active_plan): ?>
    <?php
        $days_left  = max(0, (int)ceil(($active_sub->expiry_time - time()) / 86400));
        $days_total = 0;
        if ($active_plan->expiry_type === manager::EXPIRY_DAYS) {
            $days_total = (int)$active_plan->expiry_days;
        } elseif ($active_plan->expiry_type === manager::EXPIRY_DATE && $active_plan->expiry_date) {
            $days_total = max(0, (int)ceil(($active_plan->expiry_date - $active_sub->start_time) / 86400));
        }
        $progress = $days_total > 0 ? round((($days_total - $days_left) / $days_total) * 100) : 0;
    ?>
    <div class="active-sub-card">
        <h2><?php echo get_string('active_subscription', 'local_subscriptions'); ?></h2>
        <div class="plan-title"><?php echo s($active_plan->name); ?></div>

        <span class="days-badge"><?php echo $days_left; ?> يوم متبقي</span>

        <div class="sub-meta">
            <div class="sub-meta-item">
                <span class="val"><?php echo userdate($active_sub->start_time, '%d/%m/%Y'); ?></span>
                <span class="lbl"><?php echo get_string('start_date', 'local_subscriptions'); ?></span>
            </div>
            <div class="sub-meta-item">
                <span class="val"><?php echo userdate($active_sub->expiry_time, '%d/%m/%Y'); ?></span>
                <span class="lbl"><?php echo get_string('subscription_active_until', 'local_subscriptions'); ?></span>
            </div>
            <div class="sub-meta-item">
                <span class="val"><?php echo number_format((float)$active_sub->amount_paid, 2); ?> ج</span>
                <span class="lbl"><?php echo get_string('amount_paid', 'local_subscriptions'); ?></span>
            </div>
            <div class="sub-meta-item">
                <span class="val"><?php echo $active_sub->source === 'online' ? 'إلكتروني' : 'يدوي'; ?></span>
                <span class="lbl">نوع الاشتراك</span>
            </div>
        </div>

        <!-- Progress bar -->
        <div style="background:rgba(255,255,255,0.2); border-radius:20px; height:8px; margin-bottom:18px; overflow:hidden;">
            <div style="background:#c8a84b; height:100%; border-radius:20px; width:<?php echo $progress; ?>%;"></div>
        </div>

        <!-- Included courses -->
        <?php if ($active_plan->course_access_type === manager::COURSE_ACCESS_ALL): ?>
        <div class="courses-included">
            <h3>المقررات المتاحة</h3>
            <div class="course-chips">
                <span class="course-chip">جميع المقررات</span>
            </div>
        </div>
        <?php elseif (!empty($active_course_info)): ?>
        <div class="courses-included">
            <h3>المقررات المتضمنة (<?php echo count($active_course_info); ?>)</h3>
            <div class="course-chips">
                <?php foreach ($active_course_info as $ci): ?>
                    <span class="course-chip"><?php echo s($ci->fullname); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Credit-plan: unlocked lessons -->
        <?php if ($unlock_limit > 0): ?>
        <div class="courses-included" style="margin-top:18px; border-top:1px solid rgba(255,255,255,0.2); padding-top:14px">
            <h3>
                <?php echo get_string('unlocked_lessons', 'local_subscriptions'); ?>
                (<?php echo count($unlocked_names); ?> / <?php echo (int)$unlock_limit; ?>)
                &mdash; <?php echo get_string('unlocks_remaining', 'local_subscriptions'); ?>: <?php echo (int)$unlock_remaining; ?>
            </h3>
            <?php if (!empty($unlocked_names)): ?>
            <div class="course-chips">
                <?php foreach ($unlocked_names as $ln): ?>
                    <span class="course-chip">🔓 <?php echo s($ln); ?></span>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="opacity:.85; font-size:.9em; margin:0">
                <?php echo get_string('no_unlocks_yet', 'local_subscriptions'); ?>
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php else: ?>

    <div class="no-sub-card">
        <p><?php echo get_string('no_active_subscription', 'local_subscriptions'); ?></p>
        <a href="<?php echo (new moodle_url('/local/subscriptions/index.php'))->out(); ?>"
           class="btn-browse">
            <?php echo get_string('subscribe_now', 'local_subscriptions'); ?>
        </a>
    </div>

    <?php endif; ?>

    <!-- Subscription History -->
    <?php if (!empty($all_subs)): ?>
    <div class="section-title">سجل الاشتراكات</div>
    <table class="history-table">
        <thead>
            <tr>
                <th>#</th>
                <th>الخطة</th>
                <th><?php echo get_string('start_date', 'local_subscriptions'); ?></th>
                <th><?php echo get_string('expiry_date_field', 'local_subscriptions'); ?></th>
                <th><?php echo get_string('amount_paid', 'local_subscriptions'); ?></th>
                <th><?php echo get_string('payment_method', 'local_subscriptions'); ?></th>
                <th><?php echo get_string('payment_status', 'local_subscriptions'); ?></th>
                <th>الحالة</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($all_subs as $sub): ?>
        <?php
            $pay_method = $sub->source === 'online'
                ? get_string('pay_method_online', 'local_subscriptions')
                : get_string('pay_method_offline', 'local_subscriptions');
            if ($sub->status === 'cancelled') {
                $pay_status = $sub->refund_amount
                    ? get_string('pay_status_refunded', 'local_subscriptions')
                    : get_string('pay_status_cancelled', 'local_subscriptions');
            } else {
                $pay_status = get_string('pay_status_paid', 'local_subscriptions');
            }
        ?>
        <tr>
            <td><?php echo $sub->id; ?></td>
            <td><strong><?php echo s($sub->plan_name); ?></strong></td>
            <td><?php echo $sub->start_time ? userdate($sub->start_time, '%d/%m/%Y') : '-'; ?></td>
            <td><?php echo $sub->expiry_time ? userdate($sub->expiry_time, '%d/%m/%Y') : '-'; ?></td>
            <td><?php echo number_format((float)$sub->amount_paid, 2); ?> ج</td>
            <td><?php echo $pay_method; ?></td>
            <td><?php echo $pay_status; ?></td>
            <td>
                <?php if ($sub->status === 'active'): ?>
                    <span class="badge-active"><?php echo get_string('status_active', 'local_subscriptions'); ?></span>
                <?php elseif ($sub->status === 'expired'): ?>
                    <span class="badge-expired"><?php echo get_string('status_expired', 'local_subscriptions'); ?></span>
                <?php else: ?>
                    <span class="badge-cancelled"><?php echo get_string('status_cancelled', 'local_subscriptions'); ?></span>
                <?php endif; ?>
            </td>
            <td>
                <button type="button" class="hist-toggle" data-target="det-<?php echo $sub->id; ?>"
                        style="background:#eef3f9;border:1px solid #cfe0f0;border-radius:5px;padding:3px 10px;cursor:pointer;font-size:.82em;color:#2d6a9f">
                    <?php echo get_string('details', 'local_subscriptions'); ?>
                </button>
            </td>
        </tr>
        <tr id="det-<?php echo $sub->id; ?>" class="hist-detail" style="display:none">
            <td colspan="9" style="background:#fbfcfe">
                <?php
                    $snap = !empty($sub->snapshot) ? json_decode($sub->snapshot, true) : null;
                ?>
                <div style="display:flex; gap:24px; flex-wrap:wrap; font-size:.88em; color:#444">
                    <div><strong><?php echo get_string('order_id_label', 'local_subscriptions'); ?>:</strong>
                        <?php echo s($sub->order_id ?: '-'); ?></div>
                    <div><strong><?php echo get_string('transaction_id_label', 'local_subscriptions'); ?>:</strong>
                        <?php echo s($sub->transaction_id ?: '-'); ?></div>
                    <?php if ($sub->refund_amount): ?>
                    <div><strong><?php echo get_string('refund_amount', 'local_subscriptions'); ?>:</strong>
                        <?php echo number_format((float)$sub->refund_amount, 2); ?> ج</div>
                    <?php endif; ?>
                    <?php if ($sub->status === 'cancelled' && $sub->cancel_reason): ?>
                    <div><strong><?php echo get_string('unsubscribe_reason', 'local_subscriptions'); ?>:</strong>
                        <?php echo s($sub->cancel_reason); ?></div>
                    <?php endif; ?>
                    <?php if ($snap && isset($snap['price'])): ?>
                    <div><strong><?php echo get_string('plan_price', 'local_subscriptions'); ?> (<?php echo get_string('at_purchase', 'local_subscriptions'); ?>):</strong>
                        <?php echo number_format((float)$snap['price'], 2); ?> ج</div>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="section-title">سجل الاشتراكات</div>
    <p style="color:#888; padding:20px 0">لا يوجد سجل اشتراكات حتى الآن.</p>
    <?php endif; ?>

    <p style="margin-top:20px">
        <a href="<?php echo (new moodle_url('/local/subscriptions/index.php'))->out(); ?>"
           style="color:#2d6a9f; text-decoration:none; font-size:.95em">
            &larr; <?php echo get_string('back_to_plans', 'local_subscriptions'); ?>
        </a>
    </p>
</div>

<script>
(function () {
  document.querySelectorAll('.hist-toggle').forEach(function (b) {
    b.addEventListener('click', function () {
      var row = document.getElementById(b.dataset.target);
      if (row) { row.style.display = (row.style.display === 'none') ? '' : 'none'; }
    });
  });
})();
</script>

<?php echo $OUTPUT->footer(); ?>
