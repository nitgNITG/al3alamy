<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin: manually assign a subscription to one or more users (offline payment).
 * Supports live search by name / e-mail / phone and bulk multi-user assignment.
 *
 * @package    local_subscriptions
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

defined('MOODLE_INTERNAL') || die();

require_login();
$context = context_system::instance();
require_capability('local/subscriptions:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/subscriptions/admin/assign.php'));
$PAGE->set_pagelayout('admin');

use local_subscriptions\manager;

// ── Data ─────────────────────────────────────────────────────────────────────

$plans = manager::get_plans(false);

// Load users with phone fields for live search.
$users_raw = $DB->get_records('user',
    ['deleted' => 0],
    'lastname ASC, firstname ASC',
    'id, firstname, lastname, email, username, phone1, phone2'
);

// Build a lightweight JSON array for the client-side search.
$users_json = [];
foreach ($users_raw as $u) {
    $users_json[] = [
        'id'       => (int)$u->id,
        'name'     => trim($u->firstname . ' ' . $u->lastname),
        'email'    => $u->email,
        'username' => $u->username,
        'phone'    => trim($u->phone1 . ' ' . $u->phone2),
    ];
}

// ── Handle POST ───────────────────────────────────────────────────────────────

$errors  = [];
$results = [];   // Per-user outcome after bulk confirm.
$review  = null; // Holds data for the review/confirm step.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $planid     = required_param('planid',  PARAM_INT);
    $note       = trim(optional_param('note',    '', PARAM_TEXT));
    $amount_raw = optional_param('amount',  '', PARAM_RAW_TRIMMED);
    $confirm    = optional_param('confirm', 0,  PARAM_INT);
    $userids_raw = optional_param('userids', '', PARAM_TEXT); // comma-separated IDs

    // Parse selected user IDs.
    $selected_ids = array_filter(array_map('intval', explode(',', $userids_raw)));
    $selected_ids = array_values(array_unique($selected_ids));

    // Resolve the plan.
    $plan = manager::get_plan($planid);
    if (!$plan) {
        $errors[] = get_string('assign_invalid_plan', 'local_subscriptions');
    }

    if (empty($selected_ids)) {
        $errors[] = 'الرجاء اختيار مستخدم واحد على الأقل.';
    }

    // Validate amount.
    $amount = ($amount_raw === '') ? (float)($plan->price ?? 0) : (float)$amount_raw;
    if ($amount < 0) {
        $errors[] = get_string('assign_amount_invalid', 'local_subscriptions');
    }

    // Resolve user records.
    $selected_users = [];
    if (empty($errors) && !empty($selected_ids)) {
        list($insql, $inparams) = $DB->get_in_or_equal($selected_ids);
        $selected_users = $DB->get_records_select('user',
            "id $insql AND deleted = 0", $inparams, 'lastname ASC, firstname ASC');
        if (count($selected_users) !== count($selected_ids)) {
            $errors[] = 'بعض المستخدمين المحددين غير موجودين.';
        }
    }

    if (empty($errors)) {
        // Annotate each user with active-sub status for the review table.
        foreach ($selected_users as &$u) {
            $u->has_active = manager::has_active_subscription((int)$u->id);
        }
        unset($u);

        if ($confirm) {
            // ── Perform bulk assignment ───────────────────────────────────────
            foreach ($selected_users as $u) {
                if ($u->has_active) {
                    $results[] = [
                        'user'    => $u,
                        'status'  => 'skipped',
                        'message' => 'تم التخطي — لديه اشتراك نشط',
                    ];
                    continue;
                }
                try {
                    manager::assign_to_user(
                        (int)$plan->id,
                        (int)$u->id,
                        $amount,
                        (int)$USER->id,
                        $note
                    );
                    $results[] = [
                        'user'   => $u,
                        'status' => 'ok',
                        'message' => 'تم التعيين بنجاح ✓',
                    ];
                } catch (\Throwable $e) {
                    $results[] = [
                        'user'    => $u,
                        'status'  => 'error',
                        'message' => 'خطأ: ' . $e->getMessage(),
                    ];
                }
            }
            // Stay on page to show results — do NOT redirect.

        } else {
            // ── Show review step ──────────────────────────────────────────────
            $review = [
                'users'   => $selected_users,
                'plan'    => $plan,
                'amount'  => $amount,
                'note'    => $note,
                'userids' => implode(',', $selected_ids),
            ];
        }
    }
}

// ── Output ────────────────────────────────────────────────────────────────────

$PAGE->set_title(get_string('assign_subscription', 'local_subscriptions'));
$PAGE->set_heading(get_string('assign_subscription', 'local_subscriptions'));

echo $OUTPUT->header();
?>
<style>
/* ── Layout ── */
.assign-wrap   { max-width: 820px; margin: 0 auto; font-family: inherit; direction: rtl; }
.assign-wrap .form-group  { margin-bottom: 20px; }
.assign-wrap label        { font-weight: 600; display: block; margin-bottom: 6px; }
.assign-wrap small        { color: #888; display: block; margin-top: 4px; }
.assign-wrap .form-control { width: 100%; padding: 8px 12px; border: 1px solid #ced4da;
    border-radius: 4px; font-size: 1em; box-sizing: border-box; }
.assign-wrap .btn          { padding: 9px 22px; border-radius: 4px; font-size: 1em;
    cursor: pointer; border: none; }
.assign-wrap .btn-primary  { background: #2d6a9f; color: #fff; }
.assign-wrap .btn-success  { background: #28a745; color: #fff; }
.assign-wrap .btn-secondary { background: #6c757d; color: #fff;
    text-decoration: none; display: inline-block; }

/* ── Alerts ── */
.alert-error { background:#f8d7da; border:1px solid #f5c6cb; color:#721c24;
    padding:10px 16px; border-radius:4px; margin-bottom:16px; }
.alert-error ul { margin:0; padding-inline-start:20px; }

/* ── Live-search widget ── */
.ls-search-wrap     { position: relative; }
.ls-search-input    { width: 100%; padding: 8px 12px; border: 1px solid #ced4da;
    border-radius: 4px; font-size: 1em; box-sizing: border-box; }
.ls-dropdown        { position: absolute; top: 100%; right: 0; left: 0; z-index: 1000;
    background: #fff; border: 1px solid #ced4da; border-top: none;
    border-radius: 0 0 4px 4px; max-height: 220px; overflow-y: auto;
    box-shadow: 0 4px 10px rgba(0,0,0,.1); display: none; }
.ls-dropdown.open   { display: block; }
.ls-option          { padding: 9px 13px; cursor: pointer; font-size: .95em;
    border-bottom: 1px solid #f0f0f0; }
.ls-option:hover,
.ls-option.active   { background: #e8f0fb; }
.ls-option .ls-name { font-weight: 600; }
.ls-option .ls-meta { font-size: .82em; color: #888; margin-top: 1px; }
.ls-no-results      { padding: 10px 13px; color: #999; font-size: .93em; }

/* ── Selected users chips ── */
.ls-chips-box       { display: flex; flex-wrap: wrap; gap: 7px; min-height: 38px;
    border: 1px solid #ced4da; border-radius: 4px; padding: 6px 8px;
    background: #fafafa; margin-top: 8px; }
.ls-chip            { display: inline-flex; align-items: center; gap: 6px;
    background: #dce8f7; color: #1a4a78; border-radius: 20px;
    padding: 4px 10px 4px 8px; font-size: .88em; font-weight: 600; }
.ls-chip-remove     { cursor: pointer; font-size: 1.1em; line-height: 1;
    color: #666; background: none; border: none; padding: 0; }
.ls-chip-remove:hover { color: #c00; }
.ls-chips-empty     { color: #aaa; font-size: .9em; align-self: center; padding: 2px 4px; }

/* ── Review table ── */
.review-table       { width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: .95em; }
.review-table th    { background: #2d6a9f; color: #fff; padding: 9px 12px; text-align: right; }
.review-table td    { padding: 8px 12px; border-bottom: 1px solid #e0e8f0; }
.review-table tr:last-child td { border-bottom: none; }
.review-table .warn { color: #856404; background: #fff3cd; border-radius: 4px;
    padding: 2px 7px; font-size: .85em; }

/* ── Results table ── */
.result-ok      { color: #155724; }
.result-skipped { color: #856404; }
.result-error   { color: #721c24; }

/* ── Review summary bar ── */
.review-bar     { background: #f0f7ff; border: 1px solid #cfe2ff; border-radius: 8px;
    padding: 14px 18px; margin-bottom: 18px; display: flex; flex-wrap: wrap; gap: 18px; }
.review-bar .rb-item .rb-lbl { font-size: .82em; color: #666; }
.review-bar .rb-item .rb-val { font-weight: 700; font-size: 1em; color: #1a4a78; }
</style>

<div class="assign-wrap">

<?php if (!empty($errors)): ?>
<div class="alert-error"><ul>
    <?php foreach ($errors as $e): ?><li><?php echo s($e); ?></li><?php endforeach; ?>
</ul></div>
<?php endif; ?>

<?php if (!empty($results)): ?>
    <!-- ── Results after bulk assign ── -->
    <h3>نتائج التعيين</h3>
    <table class="review-table">
        <thead><tr>
            <th>المستخدم</th><th>البريد الإلكتروني</th><th>النتيجة</th>
        </tr></thead>
        <tbody>
        <?php foreach ($results as $r): ?>
            <tr>
                <td><?php echo s(fullname($r['user'])); ?></td>
                <td><?php echo s($r['user']->email); ?></td>
                <td class="result-<?php echo $r['status']; ?>"><?php echo s($r['message']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <a href="<?php echo (new moodle_url('/local/subscriptions/admin/assign.php'))->out(); ?>" class="btn btn-primary">تعيين آخر</a>
    <a href="<?php echo (new moodle_url('/local/subscriptions/admin/report.php'))->out(); ?>" class="btn btn-secondary" style="margin-inline-start:8px;">عرض التقارير</a>

<?php elseif ($review): ?>
    <!-- ── Step 2: review & confirm ── -->
    <h3>مراجعة التعيين</h3>

    <div class="review-bar">
        <div class="rb-item"><div class="rb-lbl">الخطة</div><div class="rb-val"><?php echo s($review['plan']->name); ?></div></div>
        <div class="rb-item"><div class="rb-lbl">المبلغ المدفوع</div><div class="rb-val"><?php echo number_format($review['amount'], 2); ?> ج.م</div></div>
        <div class="rb-item"><div class="rb-lbl">الصلاحية</div><div class="rb-val">
            <?php if ($review['plan']->expiry_type === manager::EXPIRY_DAYS): ?>
                <?php echo (int)$review['plan']->expiry_days; ?> يوم
            <?php else: ?>
                <?php echo $review['plan']->expiry_date ? userdate($review['plan']->expiry_date, '%d/%m/%Y') : '-'; ?>
            <?php endif; ?>
        </div></div>
        <div class="rb-item"><div class="rb-lbl">عدد المستخدمين</div><div class="rb-val"><?php echo count($review['users']); ?></div></div>
    </div>

    <table class="review-table">
        <thead><tr>
            <th>المستخدم</th><th>البريد الإلكتروني</th><th>الحالة</th>
        </tr></thead>
        <tbody>
        <?php foreach ($review['users'] as $u): ?>
            <tr>
                <td><?php echo s(fullname($u)); ?></td>
                <td><?php echo s($u->email); ?></td>
                <td>
                    <?php if ($u->has_active): ?>
                        <span class="warn">⚠ لديه اشتراك نشط — سيتم التخطي</span>
                    <?php else: ?>
                        <span style="color:#155724;">✓ سيتم التعيين</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <form method="post">
        <input type="hidden" name="sesskey"  value="<?php echo sesskey(); ?>">
        <input type="hidden" name="userids"  value="<?php echo s($review['userids']); ?>">
        <input type="hidden" name="planid"   value="<?php echo (int)$review['plan']->id; ?>">
        <input type="hidden" name="amount"   value="<?php echo s((string)$review['amount']); ?>">
        <input type="hidden" name="note"     value="<?php echo s($review['note']); ?>">
        <input type="hidden" name="confirm"  value="1">
        <button type="submit" class="btn btn-success">تأكيد التعيين</button>
        <a href="<?php echo (new moodle_url('/local/subscriptions/admin/assign.php'))->out(); ?>" class="btn btn-secondary" style="margin-inline-start:8px;">
            <?php echo get_string('cancel'); ?>
        </a>
    </form>

<?php else: ?>
    <!-- ── Step 1: entry form ── -->
    <p style="color:#666;margin-bottom:20px;"><?php echo get_string('assign_intro', 'local_subscriptions'); ?></p>
    <form method="post" id="assign-form">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="userids" id="userids-hidden" value="">

        <!-- User search -->
        <div class="form-group">
            <label>المستخدمون *</label>
            <div class="ls-search-wrap">
                <input type="text" id="ls-search" class="ls-search-input" autocomplete="off"
                       placeholder="ابحث بالاسم أو البريد الإلكتروني أو رقم الهاتف…">
                <div id="ls-dropdown" class="ls-dropdown"></div>
            </div>
            <div id="ls-chips" class="ls-chips-box">
                <span class="ls-chips-empty" id="ls-chips-empty">لم يتم اختيار أي مستخدم بعد</span>
            </div>
            <small>اكتب جزءاً من الاسم أو الإيميل أو رقم الهاتف ثم اختر من القائمة — يمكن إضافة أكثر من مستخدم</small>
        </div>

        <!-- Plan -->
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

        <!-- Amount -->
        <div class="form-group">
            <label for="amount"><?php echo get_string('assign_amount', 'local_subscriptions'); ?></label>
            <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0"
                   style="max-width:220px" placeholder="<?php echo get_string('assign_amount_placeholder', 'local_subscriptions'); ?>">
            <small><?php echo get_string('assign_amount_help', 'local_subscriptions'); ?></small>
        </div>

        <!-- Note -->
        <div class="form-group">
            <label for="note"><?php echo get_string('assign_note', 'local_subscriptions'); ?></label>
            <textarea id="note" name="note" class="form-control" rows="2"></textarea>
        </div>

        <button type="submit" class="btn btn-primary">مراجعة وتأكيد</button>
        <a href="<?php echo (new moodle_url('/local/subscriptions/admin/plans.php'))->out(); ?>" class="btn btn-secondary" style="margin-inline-start:8px;">
            <?php echo get_string('back_to_plans', 'local_subscriptions'); ?>
        </a>
    </form>
<?php endif; ?>

</div>

<script>
(function () {
    // ── User data from PHP ────────────────────────────────────────────────────
    var USERS = <?php echo json_encode(array_values($users_json), JSON_UNESCAPED_UNICODE); ?>;

    var searchEl  = document.getElementById('ls-search');
    var dropEl    = document.getElementById('ls-dropdown');
    var chipsEl   = document.getElementById('ls-chips');
    var chipsEmpty = document.getElementById('ls-chips-empty');
    var hiddenEl  = document.getElementById('userids-hidden');
    var form      = document.getElementById('assign-form');

    if (!searchEl) return; // Not on step-1 page.

    var selected = {}; // { id: userObj }
    var activeIdx = -1;
    var currentMatches = [];

    // ── Helpers ───────────────────────────────────────────────────────────────
    function normalize(s) {
        return (s || '').toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function highlight(text, q) {
        if (!q) return s(text);
        var re = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        return s(text).replace(re, '<mark style="background:#fff176;padding:0;">$1</mark>');
    }

    // Basic HTML-escape (mirrors Moodle's s()).
    function s(v) {
        return String(v)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function syncHidden() {
        hiddenEl.value = Object.keys(selected).join(',');
    }

    // ── Chips ─────────────────────────────────────────────────────────────────
    function renderChips() {
        // Remove all existing chips (but keep the empty-message span).
        Array.from(chipsEl.querySelectorAll('.ls-chip')).forEach(function(c){ c.remove(); });
        var count = Object.keys(selected).length;
        chipsEmpty.style.display = count ? 'none' : '';
        Object.values(selected).forEach(function(u) {
            var chip = document.createElement('span');
            chip.className = 'ls-chip';
            chip.innerHTML = s(u.name)
                + '<button type="button" class="ls-chip-remove" data-uid="' + u.id + '" title="إزالة">×</button>';
            chipsEl.appendChild(chip);
        });
    }

    chipsEl.addEventListener('click', function(e) {
        var btn = e.target.closest('.ls-chip-remove');
        if (!btn) return;
        var uid = parseInt(btn.dataset.uid);
        delete selected[uid];
        syncHidden();
        renderChips();
    });

    // ── Dropdown ──────────────────────────────────────────────────────────────
    function openDropdown(matches, q) {
        currentMatches = matches;
        activeIdx = -1;
        dropEl.innerHTML = '';
        if (!matches.length) {
            dropEl.innerHTML = '<div class="ls-no-results">لا توجد نتائج</div>';
        } else {
            matches.forEach(function(u, i) {
                var div = document.createElement('div');
                div.className = 'ls-option' + (selected[u.id] ? ' active' : '');
                div.dataset.idx = i;
                var meta = [u.email];
                if (u.phone && u.phone.trim()) meta.push(u.phone.trim());
                div.innerHTML = '<div class="ls-name">' + highlight(u.name, q) + '</div>'
                    + '<div class="ls-meta">' + highlight(meta.join(' · '), q) + '</div>';
                div.addEventListener('mousedown', function(e) {
                    e.preventDefault(); // keep focus on input
                    selectUser(u);
                });
                dropEl.appendChild(div);
            });
        }
        dropEl.classList.add('open');
    }

    function closeDropdown() {
        dropEl.classList.remove('open');
        currentMatches = [];
        activeIdx = -1;
    }

    function selectUser(u) {
        selected[u.id] = u;
        syncHidden();
        renderChips();
        searchEl.value = '';
        closeDropdown();
        searchEl.focus();
    }

    // ── Search ────────────────────────────────────────────────────────────────
    searchEl.addEventListener('input', function() {
        var q = normalize(this.value);
        if (q.length < 1) { closeDropdown(); return; }
        var matches = USERS.filter(function(u) {
            return normalize(u.name).indexOf(q) >= 0
                || normalize(u.email).indexOf(q) >= 0
                || normalize(u.phone).indexOf(q) >= 0;
        }).slice(0, 30);
        openDropdown(matches, q);
    });

    // Keyboard navigation.
    searchEl.addEventListener('keydown', function(e) {
        if (!dropEl.classList.contains('open')) return;
        var opts = dropEl.querySelectorAll('.ls-option');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIdx = Math.min(activeIdx + 1, opts.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIdx = Math.max(activeIdx - 1, 0);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIdx >= 0 && currentMatches[activeIdx]) {
                selectUser(currentMatches[activeIdx]);
            }
            return;
        } else if (e.key === 'Escape') {
            closeDropdown(); return;
        }
        opts.forEach(function(o, i) {
            o.classList.toggle('highlighted', i === activeIdx);
            if (i === activeIdx) o.scrollIntoView({ block: 'nearest' });
        });
    });

    document.addEventListener('click', function(e) {
        if (!searchEl.contains(e.target) && !dropEl.contains(e.target)) closeDropdown();
    });

    // ── Form validation ───────────────────────────────────────────────────────
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!hiddenEl.value) {
                e.preventDefault();
                searchEl.focus();
                searchEl.style.borderColor = '#dc3545';
                setTimeout(function(){ searchEl.style.borderColor = ''; }, 2000);
                alert('الرجاء اختيار مستخدم واحد على الأقل.');
            }
        });
    }
})();
</script>

<?php echo $OUTPUT->footer();
