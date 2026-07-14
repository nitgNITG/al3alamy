<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin tool: force-logout users.
 *
 * Shows every user who currently has an active session (i.e. is logged in) and
 * lets an admin end all of that user's sessions with one click. The site
 * enforces one active session per user, so a stale session left behind by a
 * closed tab/private window blocks the user's next login — this clears it.
 *
 * @package    local_deviceregistration
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

admin_externalpage_setup('local_deviceregistration_forcelogout');

global $DB;

$userid = optional_param('userid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA); // logout_user
$filter = trim(optional_param('filter', '', PARAM_RAW));

$pageurl = new moodle_url('/local/deviceregistration/admin_force_logout.php');

// ── Handle logout action ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'logout_user' && $userid) {
    require_sesskey();

    $target = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', IGNORE_MISSING);
    if ($target) {
        $killed = $DB->count_records('sessions', ['userid' => $userid]);
        // Canonical kill (destroys the session in the handler + removes DB rows)...
        \core\session\manager::kill_user_sessions($userid);
        // ...plus a guaranteed sweep of any leftover rows, so the one-session
        // login check is always cleared even in edge cases.
        $DB->delete_records('sessions', ['userid' => $userid]);

        redirect(
            new moodle_url($pageurl),
            get_string('forcelogout_done', 'local_deviceregistration',
                (object) ['name' => fullname($target), 'count' => $killed]),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    redirect(new moodle_url($pageurl));
}

// ── Gather the currently logged-in users ─────────────────────────────────────
$rows = $DB->get_records_sql(
    "SELECT s.userid, COUNT(s.id) AS sessioncount, MAX(s.timemodified) AS lastactive
       FROM {sessions} s
      WHERE s.userid > 0
   GROUP BY s.userid
   ORDER BY MAX(s.timemodified) DESC");

$loggedinusers = [];
if ($rows) {
    $namefields = 'id, firstname, lastname, email, username, deleted, suspended, '
        . 'firstnamephonetic, lastnamephonetic, middlename, alternatename';
    $users = $DB->get_records_list('user', 'id', array_keys($rows), '', $namefields);
    foreach ($rows as $uid => $r) {
        if (empty($users[$uid]) || $users[$uid]->deleted) {
            continue;
        }
        $u = $users[$uid];
        // Optional client-typed filter (name / email / username).
        if ($filter !== '') {
            $hay = core_text::strtolower(fullname($u) . ' ' . $u->email . ' ' . $u->username);
            if (strpos($hay, core_text::strtolower($filter)) === false) {
                continue;
            }
        }
        $u->sessioncount = (int) $r->sessioncount;
        $u->lastactive   = (int) $r->lastactive;
        $loggedinusers[] = $u;
    }
}

$datefmt = get_string('strftimedatetimeshort', 'langconfig');

echo $OUTPUT->header();
?>
<style>
.fl-page { max-width: 820px; }
.fl-filter { display:flex; gap:8px; margin-bottom: 18px; }
.fl-filter input[type=text] { flex:1; padding:8px 12px; border:1px solid #ced4da; border-radius:4px; }
.fl-filter button { padding:8px 18px; background:#2d6a9f; color:#fff; border:none; border-radius:4px; cursor:pointer; }
.fl-filter a { padding:8px 14px; color:#2d6a9f; text-decoration:none; align-self:center; }
.fl-count { color:#555; font-size:.9em; margin-bottom:12px; }
.fl-table { width:100%; border-collapse:collapse; font-size:.92em; }
.fl-table th, .fl-table td { padding:9px 12px; border:1px solid #dee2e6; text-align:start; vertical-align:middle; }
.fl-table thead th { background:#2d6a9f; color:#fff; font-weight:600; }
.fl-table tbody tr:nth-child(even) { background:#f8f9fa; }
.fl-uname { font-weight:700; color:#1a1a1a; }
.fl-umail { color:#888; font-size:.9em; }
.fl-badge { display:inline-block; background:#eef3f9; color:#2d6a9f; border-radius:10px; padding:2px 10px; font-size:.85em; font-weight:700; }
.btn-force { background:#dc3545; color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:.86em; font-weight:600; cursor:pointer; }
.btn-force:hover { background:#c82333; }
.fl-empty { text-align:center; color:#888; padding:40px 20px; font-size:1.05em; }

/* Confirmation modal */
.fl-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:100000; }
.fl-modal-overlay.open { display:flex; align-items:center; justify-content:center; }
.fl-modal-box { background:#fff; width:100%; max-width:420px; margin:16px; border-radius:12px; box-shadow:0 12px 40px rgba(0,0,0,.25); overflow:hidden; animation:fl-pop .18s ease-out; font-family:'Segoe UI',Tahoma,Arial,sans-serif; }
@keyframes fl-pop { from { transform:scale(.94); opacity:0; } to { transform:scale(1); opacity:1; } }
.fl-modal-head { display:flex; align-items:center; gap:12px; padding:20px 22px 0; }
.fl-modal-icon { width:44px; height:44px; border-radius:50%; background:#fdecea; color:#dc3545; display:flex; align-items:center; justify-content:center; font-size:24px; flex:none; }
.fl-modal-head h4 { margin:0; font-size:1.15em; color:#222; }
.fl-modal-body { padding:12px 22px 4px; color:#555; font-size:.95em; line-height:1.6; }
.fl-modal-user { margin-top:10px; background:#f7f9fc; border:1px solid #e6edf5; border-radius:8px; padding:8px 12px; font-size:.92em; color:#333; font-weight:600; }
.fl-modal-actions { display:flex; gap:10px; padding:18px 22px 22px; }
.fl-modal-actions button { flex:1; padding:10px; border:none; border-radius:8px; font-size:1em; font-weight:600; cursor:pointer; }
.fl-modal-actions .confirm { background:#dc3545; color:#fff; }
.fl-modal-actions .confirm:hover { background:#c82333; }
.fl-modal-actions .cancel { background:#eef0f2; color:#333; }
.fl-modal-actions .cancel:hover { background:#e2e5e8; }
</style>

<div class="fl-page">
  <p class="text-muted"><?php echo get_string('forcelogout_intro', 'local_deviceregistration'); ?></p>

  <form method="get" class="fl-filter">
    <input type="text" name="filter" autocomplete="off" placeholder="<?php echo s(get_string('forcelogout_filter_placeholder', 'local_deviceregistration')); ?>" value="<?php echo s($filter); ?>">
    <button type="submit"><?php echo get_string('forcelogout_filter_btn', 'local_deviceregistration'); ?></button>
    <?php if ($filter !== ''): ?>
      <a href="<?php echo $pageurl->out(); ?>"><?php echo get_string('forcelogout_clear', 'local_deviceregistration'); ?></a>
    <?php endif; ?>
  </form>

  <?php if (empty($loggedinusers)): ?>
    <div class="fl-empty">
      <?php echo $filter !== ''
        ? get_string('forcelogout_nomatch', 'local_deviceregistration')
        : get_string('forcelogout_none_loggedin', 'local_deviceregistration'); ?>
    </div>
  <?php else: ?>
    <p class="fl-count"><?php echo get_string('forcelogout_count', 'local_deviceregistration', count($loggedinusers)); ?></p>
    <table class="fl-table">
      <thead>
        <tr>
          <th><?php echo get_string('forcelogout_col_user', 'local_deviceregistration'); ?></th>
          <th><?php echo get_string('forcelogout_col_sessions', 'local_deviceregistration'); ?></th>
          <th><?php echo get_string('forcelogout_col_lastactive', 'local_deviceregistration'); ?></th>
          <th><?php echo get_string('actions', 'local_deviceregistration'); ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($loggedinusers as $u): ?>
        <tr>
          <td>
            <div class="fl-uname"><?php echo s(fullname($u)); ?></div>
            <div class="fl-umail"><?php echo s($u->email); ?> · <?php echo s($u->username); ?></div>
          </td>
          <td><span class="fl-badge"><?php echo $u->sessioncount; ?></span></td>
          <td><?php echo userdate($u->lastactive, $datefmt); ?></td>
          <td>
            <form method="post" style="margin:0">
              <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
              <input type="hidden" name="action" value="logout_user">
              <input type="hidden" name="userid" value="<?php echo (int)$u->id; ?>">
              <button type="button" class="btn-force" data-fl-confirm
                      data-user="<?php echo s(fullname($u) . ' — ' . $u->email); ?>">
                <?php echo get_string('forcelogout_action', 'local_deviceregistration'); ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Confirmation modal -->
<div class="fl-modal-overlay" id="fl-modal">
  <div class="fl-modal-box" role="dialog" aria-modal="true" aria-labelledby="fl-modal-title">
    <div class="fl-modal-head">
      <div class="fl-modal-icon">&#9888;</div>
      <h4 id="fl-modal-title"><?php echo get_string('forcelogout_confirm_title', 'local_deviceregistration'); ?></h4>
    </div>
    <div class="fl-modal-body">
      <p style="margin:0"><?php echo get_string('forcelogout_confirm_all', 'local_deviceregistration'); ?></p>
      <div class="fl-modal-user" id="fl-modal-user"></div>
    </div>
    <div class="fl-modal-actions">
      <button type="button" class="confirm" id="fl-modal-confirm"><?php echo get_string('forcelogout_confirm_yes', 'local_deviceregistration'); ?></button>
      <button type="button" class="cancel" id="fl-modal-cancel"><?php echo get_string('cancel'); ?></button>
    </div>
  </div>
</div>

<script>
(function () {
  var overlay    = document.getElementById('fl-modal');
  if (!overlay) return;
  var userEl     = document.getElementById('fl-modal-user');
  var confirmBtn = document.getElementById('fl-modal-confirm');
  var cancelBtn  = document.getElementById('fl-modal-cancel');
  var pendingForm = null;

  function openModal(form, user) {
    pendingForm = form;
    userEl.textContent = user || '';
    overlay.classList.add('open');
  }
  function closeModal() { overlay.classList.remove('open'); pendingForm = null; }

  document.querySelectorAll('[data-fl-confirm]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openModal(btn.closest('form'), btn.getAttribute('data-user'));
    });
  });
  confirmBtn.addEventListener('click', function () {
    if (pendingForm) {
      if (pendingForm.requestSubmit) { pendingForm.requestSubmit(); } else { pendingForm.submit(); }
    }
  });
  cancelBtn.addEventListener('click', closeModal);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
})();
</script>
<?php
echo $OUTPUT->footer();
