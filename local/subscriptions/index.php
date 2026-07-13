<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();

use local_subscriptions\manager;

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/subscriptions/index.php'));
$PAGE->set_title(get_string('plans_list', 'local_subscriptions'));
$PAGE->set_heading(get_string('plans_list', 'local_subscriptions'));
$PAGE->set_pagelayout('standard');

$plans = manager::get_plans(true); // Active only.
$user_sub = manager::get_active_subscription($USER->id);

echo $OUTPUT->header();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
</head>
<body>
<style>
.subs-page { direction: rtl; font-family: 'Segoe UI', Tahoma, Arial, sans-serif; max-width: 1100px; margin: 0 auto; padding: 20px; }
.subs-page h1 { color: #2d6a9f; margin-bottom: 6px; font-size: 1.8em; }
.subs-page .subtitle { color: #666; margin-bottom: 30px; font-size: 1em; }
.plans-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; }
.plan-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    display: flex;
    flex-direction: column;
    transition: box-shadow 0.2s;
}
.plan-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.12); }
.plan-card .plan-name { font-size: 1.25em; font-weight: 700; color: #1a1a1a; margin-bottom: 8px; }
.plan-card .plan-price {
    font-size: 2em;
    font-weight: 800;
    color: #c8a84b;
    margin-bottom: 12px;
}
.plan-card .plan-price span { font-size: .45em; font-weight: 400; color: #888; vertical-align: middle; margin-right: 4px; }
.plan-card .plan-desc { color: #555; font-size: .93em; line-height: 1.6; flex: 1; margin-bottom: 14px; }
.plan-card .plan-meta { font-size: .85em; color: #666; margin-bottom: 14px; }
.plan-card .plan-meta span { display: inline-block; background: #f0f4fa; border-radius: 6px; padding: 3px 10px; margin-left: 6px; margin-bottom: 4px; }
.btn-subscribe {
    display: block;
    text-align: center;
    background: #2d6a9f;
    color: #fff;
    padding: 11px;
    border-radius: 8px;
    font-size: 1em;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.2s;
}
.btn-subscribe:hover { background: #1d5080; color: #fff; }
.btn-subscribed {
    display: block;
    text-align: center;
    background: #28a745;
    color: #fff;
    padding: 11px;
    border-radius: 8px;
    font-size: 1em;
    font-weight: 600;
    cursor: default;
}
.active-banner {
    background: linear-gradient(135deg, #2d6a9f, #1d5080);
    color: #fff;
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.active-banner .info strong { font-size: 1.15em; display: block; margin-bottom: 4px; }
.active-banner .info small { opacity: .85; }
.active-banner a { background: rgba(255,255,255,0.2); color: #fff; padding: 8px 18px; border-radius: 6px; text-decoration: none; font-size: .9em; }
.active-banner a:hover { background: rgba(255,255,255,0.35); }
.no-plans { text-align: center; padding: 60px 20px; color: #888; font-size: 1.1em; }
</style>

<div class="subs-page">
    <h1><?php echo get_string('plans_list', 'local_subscriptions'); ?></h1>
    <p class="subtitle">اختر الخطة المناسبة لك واستمتع بالوصول إلى المحتوى التعليمي</p>

    <?php if ($user_sub): ?>
    <?php
        $sub_plan = manager::get_plan($user_sub->planid);
        $days_left = max(0, (int)ceil(($user_sub->expiry_time - time()) / 86400));
    ?>
    <div class="active-banner">
        <div class="info">
            <strong>لديك اشتراك فعال: <?php echo s($sub_plan ? $sub_plan->name : 'خطة'); ?></strong>
            <small>
                <?php echo get_string('subscription_active_until', 'local_subscriptions'); ?>:
                <?php echo userdate($user_sub->expiry_time, '%d/%m/%Y'); ?>
                (<?php echo $days_left; ?> يوم متبقي)
            </small>
        </div>
        <a href="<?php echo (new moodle_url('/local/subscriptions/mysubscriptions.php'))->out(); ?>">
            <?php echo get_string('my_subscriptions', 'local_subscriptions'); ?>
        </a>
    </div>
    <?php endif; ?>

    <?php if (empty($plans)): ?>
        <div class="no-plans">
            <p><?php echo get_string('no_plans', 'local_subscriptions'); ?></p>
        </div>
    <?php else: ?>
    <div class="plans-grid">
        <?php foreach ($plans as $plan): ?>
        <?php
            $item_count = $DB->count_records('local_subscriptions_items', ['planid' => $plan->id]);
        ?>
        <div class="plan-card">
            <a href="<?php echo (new moodle_url('/local/subscriptions/plan.php', ['id' => $plan->id]))->out(); ?>"
               style="text-decoration:none; color:inherit">
            <div class="plan-name"><?php echo s($plan->name); ?></div>
            <div class="plan-price">
                <?php echo number_format((float)$plan->price, 0); ?>
                <span>جنيه مصري</span>
            </div>
            <?php if ($plan->description): ?>
                <div class="plan-desc"><?php echo nl2br(s($plan->description)); ?></div>
            <?php endif; ?>
            <div class="plan-meta">
                <?php if ($plan->course_access_type === manager::COURSE_ACCESS_ALL): ?>
                    <span>جميع المقررات</span>
                <?php else: ?>
                    <span><?php echo $item_count; ?> مقرر</span>
                <?php endif; ?>

                <?php if ($plan->expiry_type === manager::EXPIRY_DAYS): ?>
                    <span><?php echo (int)$plan->expiry_days; ?> يوم</span>
                <?php else: ?>
                    <span>حتى <?php echo $plan->expiry_date ? userdate($plan->expiry_date, '%d/%m/%Y') : '-'; ?></span>
                <?php endif; ?>
            </div>
            </a>

            <?php if ($user_sub): ?>
                <div class="btn-subscribed">
                    ✓ <?php echo get_string('already_subscribed', 'local_subscriptions'); ?>
                </div>
            <?php else: ?>
                <a href="<?php echo (new moodle_url('/local/subscriptions/buy.php', ['planid' => $plan->id]))->out(); ?>"
                   class="btn-subscribe">
                    <?php echo get_string('subscribe_now', 'local_subscriptions'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>

<?php echo $OUTPUT->footer(); ?>
