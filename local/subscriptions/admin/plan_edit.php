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

$planid = optional_param('id', 0, PARAM_INT);
$plan   = null;
$items  = [];

if ($planid) {
    $plan  = manager::get_plan($planid);
    if (!$plan) {
        throw new \moodle_exception('invalidrecord', '', '', 'Plan not found');
    }
    $items = manager::get_plan_items($planid);
}

// Build items indexed by courseid for easy lookup.
$items_by_course = [];
foreach ($items as $item) {
    $items_by_course[$item->courseid][] = $item;
}

// Get all available courses.
$all_courses = $DB->get_records('course', ['visible' => 1], 'fullname ASC', 'id, shortname, fullname');
// Remove site course.
unset($all_courses[SITEID]);

// Get modules for each course (used when lesson_access_type = specific).
// We'll load them via JS/AJAX from a simple inline JSON to keep it server-side rendered.
$course_modules_json = [];
foreach ($all_courses as $c) {
    $cms = $DB->get_records_sql(
        "SELECT cm.id, m.name AS modtype, cm.instance
         FROM {course_modules} cm
         JOIN {modules} m ON m.id = cm.module
         WHERE cm.course = :courseid AND cm.visible = 1
         ORDER BY cm.section, cm.id",
        ['courseid' => $c->id]
    );
    $mods = [];
    foreach ($cms as $cm) {
        // Get the name of the module instance.
        try {
            $modname = $DB->get_field($cm->modtype, 'name', ['id' => $cm->instance]);
        } catch (\Throwable $e) {
            $modname = $cm->modtype . ' #' . $cm->instance;
        }
        $mods[] = ['id' => $cm->id, 'name' => $modname ?: ($cm->modtype . ' #' . $cm->instance)];
    }
    $course_modules_json[$c->id] = $mods;
}

$errors = [];

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $name               = trim(required_param('name', PARAM_TEXT));
    $description        = optional_param('description', '', PARAM_TEXT);
    $price              = (float)required_param('price', PARAM_FLOAT);
    $status             = required_param('status', PARAM_ALPHA);
    $course_access_type = required_param('course_access_type', PARAM_ALPHA);
    $expiry_type        = required_param('expiry_type', PARAM_ALPHA);
    $expiry_days        = optional_param('expiry_days', null, PARAM_INT);
    $expiry_date_str    = optional_param('expiry_date', '', PARAM_TEXT);

    // Validation.
    if (empty($name)) {
        $errors[] = 'اسم الخطة مطلوب.';
    }
    if ($price < 0) {
        $errors[] = 'السعر يجب أن يكون 0 أو أكثر.';
    }
    if (!in_array($status, [manager::STATUS_ACTIVE, manager::STATUS_INACTIVE])) {
        $errors[] = 'حالة غير صالحة.';
    }
    if (!in_array($expiry_type, [manager::EXPIRY_DAYS, manager::EXPIRY_DATE])) {
        $errors[] = 'نوع انتهاء الصلاحية غير صالح.';
    }

    $expiry_date_ts = null;
    if ($expiry_type === manager::EXPIRY_DAYS) {
        if (!$expiry_days || $expiry_days < 1) {
            $errors[] = 'المدة بالأيام مطلوبة وأكبر من 0.';
        }
    } else {
        if (empty($expiry_date_str)) {
            $errors[] = 'تاريخ انتهاء الصلاحية مطلوب.';
        } else {
            $expiry_date_ts = strtotime($expiry_date_str);
            if (!$expiry_date_ts) {
                $errors[] = 'تاريخ انتهاء الصلاحية غير صالح.';
            }
        }
    }

    // Parse submitted course items.
    $submitted_items = [];
    if ($course_access_type === manager::COURSE_ACCESS_SPECIFIC) {
        $selected_courses = $_POST['courses'] ?? [];
        if (is_array($selected_courses)) {
            foreach ($selected_courses as $cid) {
                $cid = (int)$cid;
                if (!$cid) continue;
                $lat = $_POST['lesson_access_type_' . $cid] ?? manager::LESSON_ACCESS_ALL;
                if (!in_array($lat, [manager::LESSON_ACCESS_ALL, manager::LESSON_ACCESS_SPECIFIC])) {
                    $lat = manager::LESSON_ACCESS_ALL;
                }
                if ($lat === manager::LESSON_ACCESS_SPECIFIC) {
                    // One item per selected cmid.
                    $selected_cms = $_POST['cms_' . $cid] ?? [];
                    if (!empty($selected_cms) && is_array($selected_cms)) {
                        foreach ($selected_cms as $cmid) {
                            $submitted_items[] = [
                                'courseid'           => $cid,
                                'lesson_access_type' => manager::LESSON_ACCESS_SPECIFIC,
                                'cmid'               => (int)$cmid,
                            ];
                        }
                    } else {
                        // Fallback to all lessons if none selected.
                        $submitted_items[] = [
                            'courseid'           => $cid,
                            'lesson_access_type' => manager::LESSON_ACCESS_ALL,
                            'cmid'               => null,
                        ];
                    }
                } else {
                    $submitted_items[] = [
                        'courseid'           => $cid,
                        'lesson_access_type' => manager::LESSON_ACCESS_ALL,
                        'cmid'               => null,
                    ];
                }
            }
        }
    }

    if (empty($errors)) {
        $data = new \stdClass();
        $data->name               = $name;
        $data->description        = $description;
        $data->price              = $price;
        $data->status             = $status;
        $data->course_access_type = $course_access_type;
        $data->expiry_type        = $expiry_type;
        $data->expiry_days        = $expiry_type === manager::EXPIRY_DAYS ? (int)$expiry_days : null;
        $data->expiry_date        = $expiry_type === manager::EXPIRY_DATE ? $expiry_date_ts : null;
        $data->items              = $submitted_items;

        if ($planid) {
            manager::update_plan($planid, $data);
        } else {
            $planid = manager::create_plan($data);
        }

        redirect(
            new moodle_url('/local/subscriptions/admin/plans.php'),
            get_string('save_plan', 'local_subscriptions') . ' ✓',
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Re-populate form values from POST on error.
    $plan = (object)[
        'id'                 => $planid,
        'name'               => $name,
        'description'        => $description,
        'price'              => $price,
        'status'             => $status,
        'course_access_type' => $course_access_type,
        'expiry_type'        => $expiry_type,
        'expiry_days'        => $expiry_days,
        'expiry_date'        => $expiry_date_ts,
    ];
}

$page_title = $planid
    ? get_string('edit_plan', 'local_subscriptions')
    : get_string('create_plan', 'local_subscriptions');

$PAGE->set_title($page_title);
$PAGE->set_heading($page_title);

echo $OUTPUT->header();

// Pre-compute which courses/cms are selected (for re-rendering on error or edit).
$selected_course_ids = array_keys($items_by_course);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_course_ids = array_map('intval', $_POST['courses'] ?? []);
}

$expiry_date_value = '';
if (!empty($plan->expiry_date)) {
    $expiry_date_value = date('Y-m-d', $plan->expiry_date);
}

?>
<style>
.plan-form { max-width: 860px; margin: 0 auto; }
.form-group { margin-bottom: 18px; }
.form-group label { font-weight: 600; display: block; margin-bottom: 5px; }
.form-control { width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 1em; box-sizing: border-box; }
.form-control:focus { border-color: #2d6a9f; outline: none; box-shadow: 0 0 0 2px rgba(45,106,159,0.2); }
textarea.form-control { min-height: 80px; resize: vertical; }
.section-title { font-size: 1.1em; font-weight: 700; margin: 24px 0 10px; padding-bottom: 6px; border-bottom: 2px solid #2d6a9f; color: #2d6a9f; }
.radio-group label, .check-group label { font-weight: normal; display: inline-flex; align-items: center; gap: 6px; margin-left: 18px; cursor: pointer; }
.course-item { border: 1px solid #dee2e6; border-radius: 6px; padding: 12px; margin-bottom: 10px; background: #fafafa; }
.course-item .course-name { font-weight: 600; margin-bottom: 8px; }
.cms-list { margin-top: 8px; padding: 8px; background: #fff; border: 1px solid #e0e0e0; border-radius: 4px; max-height: 200px; overflow-y: auto; }
.cms-list label { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; font-weight: normal; cursor: pointer; }
.btn { padding: 9px 22px; border-radius: 4px; font-size: 1em; cursor: pointer; border: none; }
.btn-primary { background: #2d6a9f; color: #fff; }
.btn-secondary { background: #6c757d; color: #fff; text-decoration: none; display: inline-block; }
.alert-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px 16px; border-radius: 4px; margin-bottom: 16px; }
.alert-error ul { margin: 0; padding-right: 20px; }
</style>

<?php if (!empty($errors)): ?>
<div class="alert-error">
    <ul>
        <?php foreach ($errors as $e): ?>
            <li><?php echo s($e); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="plan-form">
    <form method="post" id="plan-form">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

        <!-- Basic Info -->
        <div class="section-title">معلومات الخطة</div>

        <div class="form-group">
            <label for="name"><?php echo get_string('plan_name', 'local_subscriptions'); ?> *</label>
            <input type="text" id="name" name="name" class="form-control"
                   value="<?php echo s($plan->name ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label for="description"><?php echo get_string('plan_description', 'local_subscriptions'); ?></label>
            <textarea id="description" name="description" class="form-control"><?php echo s($plan->description ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label for="price"><?php echo get_string('plan_price', 'local_subscriptions'); ?> *</label>
            <input type="number" id="price" name="price" class="form-control"
                   step="0.01" min="0"
                   value="<?php echo number_format((float)($plan->price ?? 0), 2, '.', ''); ?>" required>
        </div>

        <div class="form-group">
            <label><?php echo get_string('plan_status', 'local_subscriptions'); ?></label>
            <div class="radio-group">
                <label>
                    <input type="radio" name="status" value="active"
                        <?php echo (($plan->status ?? 'active') === 'active') ? 'checked' : ''; ?>>
                    <?php echo get_string('plan_status_active', 'local_subscriptions'); ?>
                </label>
                <label>
                    <input type="radio" name="status" value="inactive"
                        <?php echo (($plan->status ?? '') === 'inactive') ? 'checked' : ''; ?>>
                    <?php echo get_string('plan_status_inactive', 'local_subscriptions'); ?>
                </label>
            </div>
        </div>

        <!-- Expiry Settings -->
        <div class="section-title"><?php echo get_string('expiry_section', 'local_subscriptions'); ?></div>

        <div class="form-group">
            <label><?php echo get_string('expiry_type', 'local_subscriptions'); ?></label>
            <div class="radio-group">
                <label>
                    <input type="radio" name="expiry_type" value="days" id="expiry_type_days"
                        <?php echo (($plan->expiry_type ?? 'days') === 'days') ? 'checked' : ''; ?>>
                    <?php echo get_string('expiry_type_days', 'local_subscriptions'); ?>
                </label>
                <label>
                    <input type="radio" name="expiry_type" value="date" id="expiry_type_date"
                        <?php echo (($plan->expiry_type ?? '') === 'date') ? 'checked' : ''; ?>>
                    <?php echo get_string('expiry_type_date', 'local_subscriptions'); ?>
                </label>
            </div>
        </div>

        <div id="expiry_days_section" class="form-group"
             style="<?php echo (($plan->expiry_type ?? 'days') !== 'days') ? 'display:none' : ''; ?>">
            <label for="expiry_days"><?php echo get_string('expiry_days_label', 'local_subscriptions'); ?></label>
            <input type="number" id="expiry_days" name="expiry_days" class="form-control"
                   min="1" style="max-width:200px"
                   value="<?php echo (int)($plan->expiry_days ?? 30); ?>">
        </div>

        <div id="expiry_date_section" class="form-group"
             style="<?php echo (($plan->expiry_type ?? 'days') !== 'date') ? 'display:none' : ''; ?>">
            <label for="expiry_date"><?php echo get_string('expiry_date_label', 'local_subscriptions'); ?></label>
            <input type="date" id="expiry_date" name="expiry_date" class="form-control"
                   style="max-width:220px"
                   value="<?php echo s($expiry_date_value); ?>">
        </div>

        <!-- Course Access -->
        <div class="section-title"><?php echo get_string('courses_section', 'local_subscriptions'); ?></div>

        <div class="form-group">
            <label><?php echo get_string('course_access_type', 'local_subscriptions'); ?></label>
            <div class="radio-group">
                <label>
                    <input type="radio" name="course_access_type" value="all" id="cat_all"
                        <?php echo (($plan->course_access_type ?? 'specific') === 'all') ? 'checked' : ''; ?>>
                    <?php echo get_string('course_access_all', 'local_subscriptions'); ?>
                </label>
                <label>
                    <input type="radio" name="course_access_type" value="specific" id="cat_specific"
                        <?php echo (($plan->course_access_type ?? 'specific') === 'specific') ? 'checked' : ''; ?>>
                    <?php echo get_string('course_access_specific', 'local_subscriptions'); ?>
                </label>
            </div>
        </div>

        <div id="specific_courses_section"
             style="<?php echo (($plan->course_access_type ?? 'specific') !== 'specific') ? 'display:none' : ''; ?>">

            <div class="form-group">
                <label><?php echo get_string('select_courses', 'local_subscriptions'); ?></label>

                <?php if (empty($all_courses)): ?>
                    <p class="text-muted">لا توجد مقررات متاحة.</p>
                <?php else: ?>
                <?php foreach ($all_courses as $course): ?>
                <?php
                    $is_selected = in_array($course->id, $selected_course_ids);
                    // Determine lesson access type for this course.
                    $lat = manager::LESSON_ACCESS_ALL;
                    $selected_cms_for_course = [];
                    if ($is_selected && isset($items_by_course[$course->id])) {
                        foreach ($items_by_course[$course->id] as $ci) {
                            $lat = $ci->lesson_access_type;
                            if ($ci->cmid) {
                                $selected_cms_for_course[] = $ci->cmid;
                            }
                        }
                    }
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_selected) {
                        $lat = $_POST['lesson_access_type_' . $course->id] ?? manager::LESSON_ACCESS_ALL;
                        $selected_cms_for_course = array_map('intval', $_POST['cms_' . $course->id] ?? []);
                    }
                    $cms = $course_modules_json[$course->id] ?? [];
                ?>
                <div class="course-item">
                    <div class="course-name">
                        <label style="font-weight:600; cursor:pointer">
                            <input type="checkbox" name="courses[]" value="<?php echo $course->id; ?>"
                                   class="course-checkbox" data-courseid="<?php echo $course->id; ?>"
                                   <?php echo $is_selected ? 'checked' : ''; ?>>
                            <?php echo s($course->fullname); ?>
                            <small style="color:#888">(<?php echo s($course->shortname); ?>)</small>
                        </label>
                    </div>

                    <div class="course-options" id="opts_<?php echo $course->id; ?>"
                         style="<?php echo $is_selected ? '' : 'display:none'; ?> padding-right:22px;">

                        <div class="radio-group" style="margin-bottom:8px">
                            <label>
                                <input type="radio"
                                       name="lesson_access_type_<?php echo $course->id; ?>"
                                       value="all"
                                       class="lat-radio"
                                       data-courseid="<?php echo $course->id; ?>"
                                       <?php echo ($lat === manager::LESSON_ACCESS_ALL) ? 'checked' : ''; ?>>
                                <?php echo get_string('lesson_access_all', 'local_subscriptions'); ?>
                            </label>
                            <label>
                                <input type="radio"
                                       name="lesson_access_type_<?php echo $course->id; ?>"
                                       value="specific"
                                       class="lat-radio"
                                       data-courseid="<?php echo $course->id; ?>"
                                       <?php echo ($lat === manager::LESSON_ACCESS_SPECIFIC) ? 'checked' : ''; ?>>
                                <?php echo get_string('lesson_access_specific', 'local_subscriptions'); ?>
                            </label>
                        </div>

                        <?php if (!empty($cms)): ?>
                        <div class="cms-list" id="cms_list_<?php echo $course->id; ?>"
                             style="<?php echo ($lat === manager::LESSON_ACCESS_SPECIFIC) ? '' : 'display:none'; ?>">
                            <?php foreach ($cms as $cm): ?>
                            <label>
                                <input type="checkbox"
                                       name="cms_<?php echo $course->id; ?>[]"
                                       value="<?php echo $cm['id']; ?>"
                                       <?php echo in_array($cm['id'], $selected_cms_for_course) ? 'checked' : ''; ?>>
                                <?php echo s($cm['name']); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Submit -->
        <div style="margin-top:24px; display:flex; gap:12px; align-items:center">
            <button type="submit" class="btn btn-primary">
                <?php echo get_string('save_plan', 'local_subscriptions'); ?>
            </button>
            <a href="<?php echo (new moodle_url('/local/subscriptions/admin/plans.php'))->out(); ?>"
               class="btn btn-secondary">
                <?php echo get_string('back_to_plans', 'local_subscriptions'); ?>
            </a>
        </div>
    </form>
</div>

<script>
(function() {
    // Toggle expiry sections.
    document.querySelectorAll('input[name="expiry_type"]').forEach(function(r) {
        r.addEventListener('change', function() {
            document.getElementById('expiry_days_section').style.display =
                this.value === 'days' ? '' : 'none';
            document.getElementById('expiry_date_section').style.display =
                this.value === 'date' ? '' : 'none';
        });
    });

    // Toggle specific courses section.
    document.querySelectorAll('input[name="course_access_type"]').forEach(function(r) {
        r.addEventListener('change', function() {
            document.getElementById('specific_courses_section').style.display =
                this.value === 'specific' ? '' : 'none';
        });
    });

    // Toggle course options when course checkbox clicked.
    document.querySelectorAll('.course-checkbox').forEach(function(cb) {
        cb.addEventListener('change', function() {
            var courseid = this.dataset.courseid;
            var opts = document.getElementById('opts_' + courseid);
            if (opts) {
                opts.style.display = this.checked ? '' : 'none';
            }
        });
    });

    // Toggle cms list when lesson access type radio changes.
    document.querySelectorAll('.lat-radio').forEach(function(r) {
        r.addEventListener('change', function() {
            var courseid = this.dataset.courseid;
            var cmsList = document.getElementById('cms_list_' + courseid);
            if (cmsList) {
                cmsList.style.display = this.value === 'specific' ? '' : 'none';
            }
        });
    });
})();
</script>

<?php echo $OUTPUT->footer(); ?>
