<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin: manually assign a subscription to a user (offline payment).
 *
 * @package    local_subscriptions
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

defined('MOODLE_INTERNAL') || die();

require_login();
$context = context_system::instance();
require_capability('local/subscriptions:manage', $context);

admin_externalpage_setup('local_subscriptions_assign');

use local_subscriptions\manager;

$errors  = [];
$review  = null; // Holds [user, plan, amount, note] when showing the confirmation step.

// All active plans for the dropdown.
$plans = manager::get_plans(false);

// All users for the dropdown.
$users = $DB->get_records('user', ['deleted' => 0], 'lastname ASC, firstname ASC', 'id, firstname, lastname, email, username');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $useridentifier = trim(required_param('useridentifier', PARAM_TEXT));
    $planid         = required_param('planid', PARAM_INT);
    $note           = trim(optional_param('note', '', PARAM_TEXT));
    $amount_raw     = optional_param('amount', '', PARAM_RAW_TRIMMED);
    $confirm        = optional_param('confirm', 0, PARAM_INT);

    // Resolve the plan.
    $plan = manager::get_plan($planid);
    if (!$plan) {
        $errors[] = get_string('assign_invalid_plan', 'local_subscriptions');
    }

    // Resolve the user by username (from dropdown).
    $user = null;
    if ($useridentifier !== '') {
        $user = $DB->get_record('user',
            ['username' => $useridentifier, 'deleted' => 0], '*', IGNORE_MULTIPLE);
    }
    if (!$user) {
        $errors[] = get_string('assign_no_such_user', 'local_subscriptions');
    }

    // One active subscription at a time.
    if ($user && manager::has_active_subscription((int)$user->id)) {
        $errors[] = get_string('assign_user_has_active', 'local_subscriptions');
    }

    // Amount defaults to the plan price when left blank.
    $amount = ($amount_raw === '') ? (float)($plan->price ?? 0) : (float)$amount_raw;
    if ($amount < 0) {
        $errors[] = get_string('assign_amount_invalid', 'local_subscriptions');
    }

    if (empty($errors) && $confirm) {
        // Perform the assignment.
        $subid = manager::assign_to_user((int)$plan->id, (int)$user->id, $amount, (int)$USER->id, $note);
        redirect(
            new moodle_url('/local/subscriptions/admin/report.php'),
            get_string('assign_success', 'local_subscriptions'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    if (empty($errors)) {
        // Show the review/confirm step.
        $review = [
            'user'   => $user,
            'plan'   => $plan,
            'amount' => $amount,
            'note'   => $note,
            'ident'  => $useridentifier,
        ];
    }
}

$PAGE->set_title(get_string('assign_subscription', 'local_subscriptions'));
$PAGE->set_heading(get_string('assign_subscription', 'local_subscriptions'));

echo $OUTPUT->header();
?>
<style>
.assign-form { max-width: 720px; margin: 0 auto; }
.assign-form .form-group { margin-bottom: 18px; }
.assign-form label { font-weight: 600; display: block; margin-bottom: 5px; }
.assign-form .form-control { width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 1em; box-sizing: border-box; }
.assign-form small { color: #888; display: block; margin-top: 4px; }
.assign-form .btn { padding: 9px 22px; border-radius: 4px; font-size: 1em; cursor: pointer; border: none; }
.assign-form .btn-primary { background: #2d6a9f; color: #fff; }
.assign-form .btn-success { background: #28a745; color: #fff; }
.assign-form .btn-secondary { background: #6c757d; color: #fff; text-decoration: none; display: inline-block; }
.alert-error { background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:10px 16px; border-radius:4px; margin-bottom:16px; }
.alert-error ul { margin:0; padding-inline-start:20px; }
.review-card { background:#f0f7ff; border:1px solid #cfe2ff; border-radius:8px; padding:18px; margin-bottom:18px; }
.review-card .row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #e3edf9; }
.review-card .row:last-child { border-bottom:none; }
.review-card .lbl { color:#555; }
.review-card .val { font-weight:700; }
</style>

<div class="assign-form">

<?php if (!empty($errors)): ?>
<div class="alert-error"><ul>
    <?php foreach ($errors as $e): ?><li><?php echo s($e); ?></li><?php endforeach; ?>
</ul></div>
<?php endif; ?>

<?php if ($review): ?>
    <!-- Step 2: review & confirm -->
    <h3><?php echo get_string('assign_review', 'local_subscriptions'); ?></h3>
    <div class="review-card">
        <div class="row"><span class="lbl"><?php echo get_string('assign_user', 'local_subscriptions'); ?></span>
            <span class="val"><?php echo s(fullname($review['user'])); ?> (<?php echo s($review['user']->email); ?>)</span></div>
        <div class="row"><span class="lbl"><?php echo get_string('plan_name', 'local_subscriptions'); ?></span>
            <span class="val"><?php echo s($review['plan']->name); ?></span></div>
        <div class="row"><span class="lbl"><?php echo get_string('amount_paid', 'local_subscriptions'); ?></span>
            <span class="val"><?php echo number_format((float)$review['amount'], 2); ?> ج.م</span></div>
        <div class="row"><span class="lbl"><?php echo get_string('expiry_type', 'local_subscriptions'); ?></span>
            <span class="val">
                <?php if ($review['plan']->expiry_type === manager::EXPIRY_DAYS): ?>
                    <?php echo (int)$review['plan']->expiry_days; ?> <?php echo get_string('expiry_days_label', 'local_subscriptions'); ?>
                <?php else: ?>
                    <?php echo $review['plan']->expiry_date ? userdate($review['plan']->expiry_date, '%d/%m/%Y') : '-'; ?>
                <?php endif; ?>
            </span></div>
        <?php if ($review['note'] !== ''): ?>
        <div class="row"><span class="lbl"><?php echo get_string('assign_note', 'local_subscriptions'); ?></span>
            <span class="val"><?php echo s($review['note']); ?></span></div>
        <?php endif; ?>
    </div>

    <form method="post">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="useridentifier" value="<?php echo s($review['ident']); ?>">
        <input type="hidden" name="planid" value="<?php echo (int)$review['plan']->id; ?>">
        <input type="hidden" name="amount" value="<?php echo s((string)$review['amount']); ?>">
        <input type="hidden" name="note" value="<?php echo s($review['note']); ?>">
        <input type="hidden" name="confirm" value="1">
        <button type="submit" class="btn btn-success"><?php echo get_string('assign_confirm', 'local_subscriptions'); ?></button>
        <a href="<?php echo (new moodle_url('/local/subscriptions/admin/assign.php'))->out(); ?>" class="btn btn-secondary">
            <?php echo get_string('cancel'); ?>
        </a>
    </form>

<?php else: ?>
    <!-- Step 1: enter details -->
    <p style="color:#666"><?php echo get_string('assign_intro', 'local_subscriptions'); ?></p>
    <form method="post">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

        <div class="form-group">
            <label for="useridentifier"><?php echo get_string('assign_user', 'local_subscriptions'); ?> *</label>
            <select id="useridentifier" name="useridentifier" class="form-control" required>
                <option value=""><?php echo get_string('choosedots'); ?></option>
                <?php foreach ($users as $u): ?>
                    <option value="<?php echo s($u->username); ?>"
                            <?php echo (optional_param('useridentifier', '', PARAM_TEXT) === $u->username) ? 'selected' : ''; ?>>
                        <?php echo s(fullname($u)); ?> (<?php echo s($u->email); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <small><?php echo get_string('assign_user_help', 'local_subscriptions'); ?></small>
        </div>

        <div class="form-group">
            <label for="planid"><?php echo get_string('select_plan', 'local_subscriptions'); ?> *</label>
            <select id="planid" name="planid" class="form-control" required>
                <option value=""><?php echo get_string('choosedots'); ?></option>
                <?php foreach ($plans as $p): ?>
                    <option value="<?php echo (int)$p->id; ?>">
                        <?php echo s($p->name); ?> — <?php echo number_format((float)$p->price, 2); ?> ج.م
                        <?php echo $p->status !== manager::STATUS_ACTIVE ? ' (' . get_string('plan_status_inactive', 'local_subscriptions') . ')' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="amount"><?php echo get_string('assign_amount', 'local_subscriptions'); ?></label>
            <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0"
                   style="max-width:220px" placeholder="<?php echo get_string('assign_amount_placeholder', 'local_subscriptions'); ?>">
            <small><?php echo get_string('assign_amount_help', 'local_subscriptions'); ?></small>
        </div>

        <div class="form-group">
            <label for="note"><?php echo get_string('assign_note', 'local_subscriptions'); ?></label>
            <textarea id="note" name="note" class="form-control" rows="2"></textarea>
        </div>

        <button type="submit" class="btn btn-primary"><?php echo get_string('assign_review_btn', 'local_subscriptions'); ?></button>
        <a href="<?php echo (new moodle_url('/local/subscriptions/admin/plans.php'))->out(); ?>" class="btn btn-secondary">
            <?php echo get_string('back_to_plans', 'local_subscriptions'); ?>
        </a>
    </form>
<?php endif; ?>

</div>
<?php echo $OUTPUT->footer();
