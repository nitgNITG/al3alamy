<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/kashier/config.php');

require_login();
require_capability('local/subscriptions:purchase', context_system::instance());

use local_subscriptions\manager;

$planid = required_param('planid', PARAM_INT);

$plan = manager::get_plan($planid);

// Validate plan exists and is active.
if (!$plan || $plan->status !== manager::STATUS_ACTIVE) {
    redirect(
        new moodle_url('/local/subscriptions/index.php'),
        'هذه الخطة غير متاحة.',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Check expiry_date hasn't passed.
if ($plan->expiry_type === manager::EXPIRY_DATE && $plan->expiry_date && $plan->expiry_date < time()) {
    redirect(
        new moodle_url('/local/subscriptions/index.php'),
        'انتهت مدة هذه الخطة.',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Check user doesn't already have active subscription.
if (manager::has_active_subscription($USER->id)) {
    redirect(
        new moodle_url('/local/subscriptions/mysubscriptions.php'),
        get_string('already_subscribed', 'local_subscriptions'),
        null,
        \core\output\notification::NOTIFY_INFO
    );
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/subscriptions/buy.php', ['planid' => $planid]));
$PAGE->set_title(get_string('buy_subscription', 'local_subscriptions'));
$PAGE->set_heading(get_string('buy_subscription', 'local_subscriptions'));
$PAGE->set_pagelayout('standard');

// Get included courses count.
$item_count = 0;
$course_names = [];
if ($plan->course_access_type === manager::COURSE_ACCESS_SPECIFIC) {
    $items = manager::get_plan_items($planid);
    $course_ids = array_unique(array_column($items, 'courseid'));
    $item_count = count($course_ids);
    foreach ($course_ids as $cid) {
        $c = $DB->get_record('course', ['id' => $cid], 'fullname', IGNORE_MISSING);
        if ($c) {
            $course_names[] = $c->fullname;
        }
    }
}

// Handle POST: initiate Kashier payment.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $ts       = time();
    $order_id = 'sub-' . $USER->id . '-' . $planid . '-' . $ts;
    $amount   = number_format((float)$plan->price, 2, '.', '');

    $redirect_url = $CFG->wwwroot . '/kashier/callback.php';
    $webhook_url  = $CFG->wwwroot . '/kashier/webhook.php';
    $description  = 'اشتراك: ' . $plan->name . ' - ' . fullname($USER);

    // kashier_create_session(order_id, amount, redirect_url, webhook_url, description, type)
    $session = kashier_create_session(
        $order_id,
        $amount,
        $redirect_url,
        $webhook_url,
        $description,
        'student'
    );

    if (!empty($session['sessionUrl'])) {
        redirect($session['sessionUrl']);
    } else {
        $error_msg = $session['message'] ?? 'حدث خطأ أثناء الاتصال ببوابة الدفع. حاول مرة أخرى.';
        redirect(
            new moodle_url('/local/subscriptions/buy.php', ['planid' => $planid]),
            $error_msg,
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

echo $OUTPUT->header();
?>
<style>
.buy-page { direction: rtl; font-family: 'Segoe UI', Tahoma, Arial, sans-serif; max-width: 680px; margin: 30px auto; padding: 0 20px; }
.buy-card { background: #fff; border: 1px solid #dee2e6; border-radius: 14px; padding: 30px; box-shadow: 0 2px 16px rgba(0,0,0,0.08); }
.buy-card h2 { color: #2d6a9f; margin-top: 0; font-size: 1.5em; margin-bottom: 20px; }
.detail-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
.detail-row:last-of-type { border-bottom: none; }
.detail-row .label { color: #666; font-size: .95em; }
.detail-row .value { font-weight: 600; color: #1a1a1a; }
.price-big { font-size: 2.4em; font-weight: 800; color: #c8a84b; display: block; text-align: center; margin: 20px 0 8px; }
.price-big small { font-size: .4em; font-weight: 400; color: #888; vertical-align: middle; }
.courses-list { margin: 12px 0; padding: 12px 16px; background: #f8f9fa; border-radius: 8px; font-size: .9em; color: #444; line-height: 1.8; }
.btn-pay { display: block; width: 100%; padding: 14px; background: #2d6a9f; color: #fff; font-size: 1.1em; font-weight: 700; border: none; border-radius: 10px; cursor: pointer; margin-top: 22px; transition: background .2s; }
.btn-pay:hover { background: #1d5080; }
.btn-back { display: inline-block; margin-top: 14px; color: #2d6a9f; text-decoration: none; font-size: .9em; }
.secure-note { text-align: center; color: #888; font-size: .82em; margin-top: 10px; }
.kashier-logo { display: block; text-align: center; margin-top: 8px; color: #aaa; font-size: .8em; }
</style>

<div class="buy-page">
    <div class="buy-card">
        <h2><?php echo get_string('buy_subscription', 'local_subscriptions'); ?></h2>

        <span class="price-big">
            <?php echo number_format((float)$plan->price, 0); ?>
            <small>جنيه مصري</small>
        </span>

        <div class="detail-row">
            <span class="label">الخطة</span>
            <span class="value"><?php echo s($plan->name); ?></span>
        </div>

        <?php if ($plan->expiry_type === manager::EXPIRY_DAYS): ?>
        <div class="detail-row">
            <span class="label">مدة الاشتراك</span>
            <span class="value"><?php echo (int)$plan->expiry_days; ?> يوم</span>
        </div>
        <?php else: ?>
        <div class="detail-row">
            <span class="label">صالح حتى</span>
            <span class="value"><?php echo $plan->expiry_date ? userdate($plan->expiry_date, '%d/%m/%Y') : '-'; ?></span>
        </div>
        <?php endif; ?>

        <div class="detail-row">
            <span class="label">المقررات</span>
            <span class="value">
                <?php if ($plan->course_access_type === manager::COURSE_ACCESS_ALL): ?>
                    جميع المقررات
                <?php else: ?>
                    <?php echo $item_count; ?> مقرر
                <?php endif; ?>
            </span>
        </div>

        <?php if (!empty($course_names)): ?>
        <div class="courses-list">
            <?php foreach ($course_names as $cn): ?>
                &bull; <?php echo s($cn); ?><br>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($plan->description): ?>
        <div style="margin: 14px 0; padding: 12px; background: #f0f7ff; border-radius: 8px; font-size: .9em; color: #333; line-height: 1.7;">
            <?php echo nl2br(s($plan->description)); ?>
        </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <button type="submit" class="btn-pay">
                <?php echo get_string('confirm_purchase', 'local_subscriptions'); ?> &larr; الدفع الآن
            </button>
        </form>

        <div class="secure-note">الدفع آمن ومشفر عبر بوابة كاشير</div>
        <div class="kashier-logo">Powered by Kashier</div>
    </div>
    <a href="<?php echo (new moodle_url('/local/subscriptions/index.php'))->out(); ?>" class="btn-back">
        &larr; <?php echo get_string('back_to_plans', 'local_subscriptions'); ?>
    </a>
</div>

<?php echo $OUTPUT->footer(); ?>
