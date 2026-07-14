<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin tool: force-logout a user's other session(s).
 *
 * The site enforces one active session per user (checked against the core
 * {sessions} table both in login/index.php and in this plugin's
 * user_loggedin observer). When a user closes a tab/private window without
 * logging out, the stale session row blocks their next login until it times
 * out. This lets an admin find the user and clear it immediately.
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

$search  = trim(optional_param('search', '', PARAM_RAW));
$userid  = optional_param('userid', 0, PARAM_INT);
$action  = optional_param('action', '', PARAM_ALPHA); // logout_sid | logout_all
$sid     = optional_param('sid', '', PARAM_RAW);

$pageurl = new moodle_url('/local/deviceregistration/admin_force_logout.php');

// ── Handle actions ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '' && $userid) {
    require_sesskey();

    $target = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', IGNORE_MISSING);
    if ($target) {
        if ($action === 'logout_sid' && $sid !== '') {
            \core\session\manager::kill_session($sid);
            redirect(
                new moodle_url($pageurl, ['userid' => $userid]),
                get_string('forcelogout_session_done', 'local_deviceregistration'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else if ($action === 'logout_all') {
            \core\session\manager::kill_user_sessions($userid);
            redirect(
                new moodle_url($pageurl, ['userid' => $userid]),
                get_string('forcelogout_all_done', 'local_deviceregistration'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    }
}

$PAGE->set_title(get_string('forcelogout_title', 'local_deviceregistration'));
$PAGE->set_heading(get_string('forcelogout_title', 'local_deviceregistration'));

echo $OUTPUT->header();
?>
<style>
.fl-page { max-width: 760px; }
.fl-search { display:flex; gap:8px; margin-bottom: 20px; }
.fl-search input[type=text] { flex:1; padding:8px 12px; border:1px solid #ced4da; border-radius:4px; }
.fl-search button { padding:8px 18px; background:#2d6a9f; color:#fff; border:none; border-radius:4px; cursor:pointer; }
.fl-userinfo { background:#f0f7ff; border:1px solid #cfe2ff; border-radius:8px; padding:14px 18px; margin-bottom:18px; }
.fl-table { width:100%; border-collapse:collapse; font-size:.92em; margin-bottom:18px; }
.fl-table th, .fl-table td { padding:8px 12px; border:1px solid #dee2e6; text-align:start; }
.fl-table thead th { background:#2d6a9f; color:#fff; }
.btn-force { background:#dc3545; color:#fff; border:none; border-radius:4px; padding:5px 14px; font-size:.85em; cursor:pointer; }
.btn-force:hover { background:#c82333; }
.btn-force-all { background:#dc3545; color:#fff; border:none; border-radius:6px; padding:10px 20px; font-weight:600; cursor:pointer; }
.btn-force-all:hover { background:#c82333; }
.fl-nomatch { color:#888; padding: 10px 0; }
.fl-userlist a { display:block; padding:8px 12px; border:1px solid #dee2e6; border-radius:6px; margin-bottom:6px; text-decoration:none; color:#1a1a1a; }
.fl-userlist a:hover { background:#f8f9fa; }
</style>

<div class="fl-page">
  <p class="text-muted"><?php echo get_string('forcelogout_intro', 'local_deviceregistration'); ?></p>

  <form method="get" class="fl-search">
    <input type="hidden" name="userid" value="0">
    <input type="text" name="search" placeholder="<?php echo s(get_string('forcelogout_search_placeholder', 'local_deviceregistration')); ?>" value="<?php echo s($search); ?>">
    <button type="submit"><?php echo get_string('forcelogout_search_btn', 'local_deviceregistration'); ?></button>
  </form>

  <?php
  $selecteduser = null;
  if ($userid) {
      $selecteduser = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', IGNORE_MISSING);
  }

  if (!$selecteduser && $search !== ''):
      $like = $DB->sql_like('email', ':e', false) . ' OR ' . $DB->sql_like('username', ':u', false)
          . ' OR ' . $DB->sql_like($DB->sql_concat('firstname', "' '", 'lastname'), ':n', false);
      $matches = $DB->get_records_select(
          'user',
          "($like) AND deleted = 0",
          [
              'e' => '%' . $DB->sql_like_escape($search) . '%',
              'u' => '%' . $DB->sql_like_escape($search) . '%',
              'n' => '%' . $DB->sql_like_escape($search) . '%',
          ],
          'lastname, firstname',
          'id, username, email, firstname, lastname',
          0,
          20
      );
      if ($matches):
      ?>
      <div class="fl-userlist">
        <?php foreach ($matches as $m): ?>
          <a href="<?php echo (new moodle_url($pageurl, ['userid' => $m->id]))->out(); ?>">
            <strong><?php echo s(fullname($m)); ?></strong> — <?php echo s($m->email); ?> (<?php echo s($m->username); ?>)
          </a>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <p class="fl-nomatch"><?php echo get_string('forcelogout_nomatch', 'local_deviceregistration'); ?></p>
      <?php endif;
  endif;

  if ($selecteduser):
      $sessions = $DB->get_records('sessions', ['userid' => $selecteduser->id], 'timemodified DESC');
      $datefmt  = get_string('strftimedatetimeshort', 'langconfig');
  ?>
  <div class="fl-userinfo">
    <strong><?php echo s(fullname($selecteduser)); ?></strong> — <?php echo s($selecteduser->email); ?>
    (<?php echo s($selecteduser->username); ?>)
  </div>

  <?php if (empty($sessions)): ?>
    <p class="fl-nomatch"><?php echo get_string('forcelogout_nosessions', 'local_deviceregistration'); ?></p>
  <?php else: ?>
    <table class="fl-table">
      <thead>
        <tr>
          <th><?php echo get_string('forcelogout_col_started', 'local_deviceregistration'); ?></th>
          <th><?php echo get_string('forcelogout_col_lastactive', 'local_deviceregistration'); ?></th>
          <th><?php echo get_string('forcelogout_col_ip', 'local_deviceregistration'); ?></th>
          <th><?php echo get_string('actions', 'local_deviceregistration'); ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($sessions as $s): ?>
        <tr>
          <td><?php echo userdate($s->timecreated, $datefmt); ?></td>
          <td><?php echo userdate($s->timemodified, $datefmt); ?></td>
          <td><?php echo s($s->lastip ?: '—'); ?></td>
          <td>
            <form method="post" style="display:inline">
              <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
              <input type="hidden" name="userid" value="<?php echo (int)$selecteduser->id; ?>">
              <input type="hidden" name="action" value="logout_sid">
              <input type="hidden" name="sid" value="<?php echo s($s->sid); ?>">
              <button type="submit" class="btn-force"
                      onclick="return confirm('<?php echo s(get_string('forcelogout_confirm_one', 'local_deviceregistration')); ?>')">
                <?php echo get_string('forcelogout_action', 'local_deviceregistration'); ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <form method="post">
      <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
      <input type="hidden" name="userid" value="<?php echo (int)$selecteduser->id; ?>">
      <input type="hidden" name="action" value="logout_all">
      <button type="submit" class="btn-force-all"
              onclick="return confirm('<?php echo s(get_string('forcelogout_confirm_all', 'local_deviceregistration')); ?>')">
        <?php echo get_string('forcelogout_action_all', 'local_deviceregistration'); ?>
      </button>
    </form>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php
echo $OUTPUT->footer();
