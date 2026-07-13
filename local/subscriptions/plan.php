<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Student-facing subscription plan detail view (US-SB-1-1).
 *
 * @package    local_subscriptions
 */

require_once(__DIR__ . '/../../config.php');

require_login();

use local_subscriptions\manager;

$planid = required_param('id', PARAM_INT);
$plan   = manager::get_plan($planid);

if (!$plan || $plan->status !== manager::STATUS_ACTIVE) {
    redirect(new moodle_url('/local/subscriptions/index.php'),
        get_string('plan_unavailable', 'local_subscriptions'),
        null, \core\output\notification::NOTIFY_ERROR);
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/subscriptions/plan.php', ['id' => $planid]));
$PAGE->set_title(s($plan->name));
$PAGE->set_heading(s($plan->name));
$PAGE->set_pagelayout('standard');

$user_sub = manager::get_active_subscription($USER->id);
$items    = manager::get_plan_items($planid);

// Build per-course info: lesson access type + accessible lesson count / names.
// "Lessons" here are resource2 activities.
$courses_info = [];
$items_by_course = [];
foreach ($items as $it) {
    $items_by_course[$it->courseid][] = $it;
}
foreach ($items_by_course as $cid => $its) {
    $course = $DB->get_record('course', ['id' => $cid], 'id, fullname', IGNORE_MISSING);
    if (!$course) {
        continue;
    }
    // If any item for this course is "all", the whole course is all-lessons.
    $is_all = false;
    $cmids  = [];
    foreach ($its as $it) {
        if ($it->lesson_access_type === manager::LESSON_ACCESS_ALL) {
            $is_all = true;
        } else if (!empty($it->cmid)) {
            $cmids[(int)$it->cmid] = true;
        }
    }

    if ($is_all) {
        $total = $DB->count_records_sql(
            "SELECT COUNT(cm.id)
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.course = :cid AND m.name = 'resource2' AND cm.visible = 1",
            ['cid' => $cid]);
        $courses_info[] = [
            'name'    => $course->fullname,
            'access'  => get_string('lesson_access_all', 'local_subscriptions'),
            'count'   => (int)$total,
            'lessons' => [],
        ];
    } else {
        $names = [];
        foreach (array_keys($cmids) as $cmid) {
            $cm = $DB->get_record('course_modules', ['id' => $cmid], 'id, module, instance', IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $modtype = $DB->get_field('modules', 'name', ['id' => $cm->module]);
            $nm = $modtype ? $DB->get_field($modtype, 'name', ['id' => $cm->instance], IGNORE_MISSING) : '';
            $names[] = $nm ?: ('#' . $cmid);
        }
        $courses_info[] = [
            'name'    => $course->fullname,
            'access'  => get_string('lesson_access_specific', 'local_subscriptions'),
            'count'   => count($names),
            'lessons' => $names,
        ];
    }
}

echo $OUTPUT->header();
?>
<style>
.pd-page { direction:rtl; font-family:'Segoe UI',Tahoma,Arial,sans-serif; max-width:820px; margin:24px auto; padding:0 20px; }
.pd-card { background:#fff; border:1px solid #dee2e6; border-radius:14px; padding:28px; box-shadow:0 2px 16px rgba(0,0,0,.07); }
.pd-card h1 { color:#2d6a9f; margin:0 0 6px; font-size:1.7em; }
.pd-price { font-size:2.2em; font-weight:800; color:#c8a84b; margin:10px 0; }
.pd-price span { font-size:.4em; color:#888; font-weight:400; }
.pd-desc { color:#444; line-height:1.8; margin:12px 0; }
.pd-meta { display:flex; gap:12px; flex-wrap:wrap; margin:14px 0; }
.pd-meta span { background:#f0f4fa; border-radius:8px; padding:6px 14px; font-size:.9em; color:#33506e; }
.pd-section { margin-top:22px; }
.pd-section h3 { font-size:1.05em; color:#2d6a9f; border-bottom:2px solid #eaeff5; padding-bottom:6px; }
.pd-course { border:1px solid #e5e9ef; border-radius:8px; padding:12px 16px; margin-bottom:10px; }
.pd-course .cn { font-weight:700; }
.pd-course .ci { color:#666; font-size:.88em; margin-top:3px; }
.pd-course ul { margin:8px 0 0; padding-inline-start:20px; color:#444; font-size:.9em; }
.pd-credit { background:#eafaf1; border:1px solid #b8e6cc; color:#1a7a48; border-radius:8px; padding:12px 16px; margin-top:14px; font-weight:600; }
.pd-btn { display:inline-block; background:#2d6a9f; color:#fff; padding:12px 30px; border-radius:10px; font-size:1.05em; font-weight:700; text-decoration:none; margin-top:20px; }
.pd-btn:hover { background:#1d5080; color:#fff; }
.pd-owned { background:#28a745; color:#fff; padding:12px 24px; border-radius:10px; display:inline-block; margin-top:20px; font-weight:700; }
.pd-back { display:inline-block; margin-top:14px; color:#2d6a9f; text-decoration:none; }
</style>

<div class="pd-page">
  <div class="pd-card">
    <h1><?php echo s($plan->name); ?></h1>
    <div class="pd-price"><?php echo number_format((float)$plan->price, 0); ?> <span>جنيه مصري</span></div>

    <?php if ($plan->description): ?>
        <div class="pd-desc"><?php echo nl2br(s($plan->description)); ?></div>
    <?php endif; ?>

    <div class="pd-meta">
        <?php if ($plan->course_access_type === manager::COURSE_ACCESS_ALL): ?>
            <span>📚 <?php echo get_string('course_access_all', 'local_subscriptions'); ?></span>
        <?php else: ?>
            <span>📚 <?php echo count($courses_info); ?> <?php echo get_string('courses_section', 'local_subscriptions'); ?></span>
        <?php endif; ?>
        <?php if ($plan->expiry_type === manager::EXPIRY_DAYS): ?>
            <span>⏳ <?php echo (int)$plan->expiry_days; ?> <?php echo get_string('expiry_days_label', 'local_subscriptions'); ?></span>
        <?php else: ?>
            <span>⏳ <?php echo get_string('expiry_type_date', 'local_subscriptions'); ?>: <?php echo $plan->expiry_date ? userdate($plan->expiry_date, '%d/%m/%Y') : '-'; ?></span>
        <?php endif; ?>
    </div>

    <?php if ((int)$plan->unlock_limit > 0): ?>
        <div class="pd-credit">
            🔓 <?php echo get_string('plan_detail_credit', 'local_subscriptions', (int)$plan->unlock_limit); ?>
        </div>
    <?php endif; ?>

    <?php if ($plan->course_access_type === manager::COURSE_ACCESS_SPECIFIC && !empty($courses_info)): ?>
    <div class="pd-section">
        <h3><?php echo get_string('courses_section', 'local_subscriptions'); ?></h3>
        <?php foreach ($courses_info as $ci): ?>
        <div class="pd-course">
            <div class="cn"><?php echo s($ci['name']); ?></div>
            <div class="ci"><?php echo s($ci['access']); ?> — <?php echo (int)$ci['count']; ?> <?php echo get_string('plan_detail_lessons', 'local_subscriptions'); ?></div>
            <?php if (!empty($ci['lessons'])): ?>
                <ul>
                    <?php foreach ($ci['lessons'] as $ln): ?><li><?php echo s($ln); ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($user_sub): ?>
        <div class="pd-owned">✓ <?php echo get_string('already_subscribed', 'local_subscriptions'); ?></div>
    <?php else: ?>
        <a class="pd-btn" href="<?php echo (new moodle_url('/local/subscriptions/buy.php', ['planid' => $planid]))->out(); ?>">
            <?php echo get_string('subscribe_now', 'local_subscriptions'); ?>
        </a>
    <?php endif; ?>
  </div>

  <a class="pd-back" href="<?php echo (new moodle_url('/local/subscriptions/index.php'))->out(); ?>">
      &larr; <?php echo get_string('back_to_plans', 'local_subscriptions'); ?>
  </a>
</div>

<?php echo $OUTPUT->footer();
