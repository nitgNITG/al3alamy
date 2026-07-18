<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin tool — two tabs:
 *   "Active sessions"    : force-logout users who are currently logged in.
 *   "Registered devices" : browse every user's registered devices and revoke
 *                          individual device records so users can log in from
 *                          new devices without admin having to nuke all sessions.
 *
 * @package    local_deviceregistration
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

admin_externalpage_setup('local_deviceregistration_forcelogout');

global $DB;

// ── URL params ────────────────────────────────────────────────────────────────
$view        = optional_param('view',        'sessions', PARAM_ALPHA);  // sessions | devices
$userid      = optional_param('userid',      0,          PARAM_INT);
$action      = optional_param('action',      '',         PARAM_ALPHA);  // logout_user | remove_device
$filter      = trim(optional_param('filter', '',         PARAM_RAW));
$deviceuser  = optional_param('deviceuser',  0,          PARAM_INT);    // expand devices for this user
$deviceid    = optional_param('deviceid',    0,          PARAM_INT);    // device record to remove
$countfilter = optional_param('countfilter', '',         PARAM_RAW);    // e.g. "2", "at_limit", "over_limit"

$pageurl = new moodle_url('/local/deviceregistration/admin_force_logout.php');

// ── POST: force logout ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'logout_user' && $userid) {
    require_sesskey();

    $target = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', IGNORE_MISSING);
    if ($target) {
        $killed = $DB->count_records('sessions', ['userid' => $userid]);
        \core\session\manager::kill_user_sessions($userid);
        $DB->delete_records('sessions', ['userid' => $userid]);

        redirect(
            new moodle_url($pageurl, ['view' => 'sessions', 'filter' => $filter]),
            get_string('forcelogout_done', 'local_deviceregistration',
                (object) ['name' => fullname($target), 'count' => $killed]),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    redirect(new moodle_url($pageurl, ['view' => 'sessions']));
}

// ── POST: remove individual device record ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'remove_device' && $deviceid) {
    require_sesskey();

    $device = $DB->get_record('local_devreg_device', ['id' => $deviceid], '*', IGNORE_MISSING);
    if ($device) {
        $owner = $DB->get_record('user', ['id' => $device->userid, 'deleted' => 0], 'id,firstname,lastname,email', IGNORE_MISSING);
        $DB->delete_records('local_devreg_device', ['id' => $deviceid]);

        redirect(
            new moodle_url($pageurl, ['view' => 'devices', 'deviceuser' => $device->userid, 'filter' => $filter, 'countfilter' => $countfilter]),
            get_string('device_revoked', 'local_deviceregistration',
                (object) ['name' => $owner ? fullname($owner) : '?']),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    redirect(new moodle_url($pageurl, ['view' => 'devices']));
}

// ── Data: sessions tab ────────────────────────────────────────────────────────
$loggedinusers = [];
if ($view === 'sessions') {
    $rows = $DB->get_records_sql(
        "SELECT s.userid, COUNT(s.id) AS sessioncount, MAX(s.timemodified) AS lastactive
           FROM {sessions} s
          WHERE s.userid > 0
       GROUP BY s.userid
       ORDER BY MAX(s.timemodified) DESC");

    if ($rows) {
        $namefields = 'id, firstname, lastname, email, username, deleted, suspended, '
            . 'firstnamephonetic, lastnamephonetic, middlename, alternatename';
        $users = $DB->get_records_list('user', 'id', array_keys($rows), '', $namefields);
        foreach ($rows as $uid => $r) {
            if (empty($users[$uid]) || $users[$uid]->deleted) {
                continue;
            }
            $u = $users[$uid];
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
}

// ── Data: devices tab ─────────────────────────────────────────────────────────
$deviceusers   = [];
$selecteddevices = [];
$selecteduser  = null;
$max           = local_deviceregistration_max_devices();

if ($view === 'devices') {
    // All users who have at least one registered device.
    $devrows = $DB->get_records_sql(
        "SELECT d.userid, COUNT(d.id) AS devicecount, MAX(d.timelastseen) AS lastseen
           FROM {local_devreg_device} d
       GROUP BY d.userid
       ORDER BY MAX(d.timelastseen) DESC"
    );

    if ($devrows) {
        $namefields = 'id, firstname, lastname, email, username, deleted, '
            . 'firstnamephonetic, lastnamephonetic, middlename, alternatename';
        $uids  = array_keys($devrows);
        $users = $DB->get_records_list('user', 'id', $uids, '', $namefields);
        foreach ($devrows as $uid => $r) {
            if (empty($users[$uid]) || $users[$uid]->deleted) {
                continue;
            }
            $u = $users[$uid];
            $u->devicecount = (int) $r->devicecount;
            $u->lastseen    = (int) $r->lastseen;

            // ── Text filter (name / email / username) ──
            if ($filter !== '') {
                $hay = core_text::strtolower(fullname($u) . ' ' . $u->email . ' ' . $u->username);
                if (strpos($hay, core_text::strtolower($filter)) === false) {
                    continue;
                }
            }

            // ── Device-count filter ────────────────────
            if ($countfilter !== '') {
                if ($countfilter === 'at_limit') {
                    if (!($max > 0 && $u->devicecount >= $max)) continue;
                } elseif ($countfilter === 'under_limit') {
                    if (!($max <= 0 || $u->devicecount < $max)) continue;
                } elseif (is_numeric($countfilter)) {
                    if ($u->devicecount !== (int) $countfilter) continue;
                }
            }

            $deviceusers[] = $u;
        }
    }

    // If a specific user is selected, load their device records.
    if ($deviceuser > 0) {
        $selecteduser    = $DB->get_record('user', ['id' => $deviceuser, 'deleted' => 0],
            'id,firstname,lastname,email,username', IGNORE_MISSING);
        $selecteddevices = $DB->get_records('local_devreg_device',
            ['userid' => $deviceuser], 'timelastseen DESC');
    }
}

$datefmt = get_string('strftimedatetimeshort', 'langconfig');

echo $OUTPUT->header();
?>
<style>
/* ── Shared layout ────────────────────────────────────────── */
.dr-page   { max-width: 900px; }
.dr-tabs   { display:flex; gap:4px; margin-bottom:24px; border-bottom:2px solid #dee2e6; }
.dr-tab    { padding:9px 22px; font-weight:600; font-size:.93em; color:#555; text-decoration:none;
             border:1px solid transparent; border-bottom:none; border-radius:6px 6px 0 0; }
.dr-tab:hover  { color:#2d6a9f; background:#f0f4f9; }
.dr-tab.active { color:#2d6a9f; background:#fff; border-color:#dee2e6; margin-bottom:-2px; }

/* ── Shared table ─────────────────────────────────────────── */
.dr-filter  { display:flex; gap:8px; margin-bottom:18px; }
.dr-filter input[type=text] { flex:1; padding:8px 12px; border:1px solid #ced4da; border-radius:4px; }
.dr-filter button { padding:8px 18px; background:#2d6a9f; color:#fff; border:none; border-radius:4px; cursor:pointer; }
.dr-filter a { padding:8px 14px; color:#2d6a9f; text-decoration:none; align-self:center; }
.dr-select { padding:8px 10px; border:1px solid #ced4da; border-radius:4px; background:#fff; font-size:.92em; }
.dr-count   { color:#555; font-size:.9em; margin-bottom:12px; }
.dr-table   { width:100%; border-collapse:collapse; font-size:.92em; }
.dr-table th, .dr-table td { padding:9px 12px; border:1px solid #dee2e6; text-align:start; vertical-align:middle; }
.dr-table thead th { background:#2d6a9f; color:#fff; font-weight:600; }
.dr-table tbody tr:nth-child(even) { background:#f8f9fa; }
.dr-uname  { font-weight:700; color:#1a1a1a; }
.dr-umail  { color:#888; font-size:.9em; }
.dr-badge  { display:inline-block; background:#eef3f9; color:#2d6a9f; border-radius:10px; padding:2px 10px; font-size:.85em; font-weight:700; }
.dr-badge.warn { background:#fff3cd; color:#856404; }
.dr-empty  { text-align:center; color:#888; padding:40px 20px; font-size:1.05em; }

/* ── Action buttons ───────────────────────────────────────── */
.btn-force  { background:#dc3545; color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:.86em; font-weight:600; cursor:pointer; }
.btn-force:hover { background:#c82333; }
.btn-manage { background:#2d6a9f; color:#fff; border:none; border-radius:5px; padding:6px 14px; font-size:.86em; font-weight:600; text-decoration:none; }
.btn-manage:hover { background:#245580; color:#fff; }
.btn-revoke { background:#fff; color:#dc3545; border:1px solid #dc3545; border-radius:5px; padding:5px 14px; font-size:.85em; font-weight:600; cursor:pointer; }
.btn-revoke:hover { background:#dc3545; color:#fff; }

/* ── Device detail panel ──────────────────────────────────── */
.dr-detail  { background:#f7f9fc; border:1px solid #d0dce8; border-radius:8px; padding:18px 20px; margin-bottom:24px; }
.dr-detail h4 { margin:0 0 12px; color:#2d6a9f; font-size:1em; }
.dr-ua      { font-size:.82em; color:#555; max-width:300px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

/* ── Modal ────────────────────────────────────────────────── */
.dr-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:100000; }
.dr-modal-overlay.open { display:flex; align-items:center; justify-content:center; }
.dr-modal-box   { background:#fff; width:100%; max-width:420px; margin:16px; border-radius:12px; box-shadow:0 12px 40px rgba(0,0,0,.25); overflow:hidden; animation:dr-pop .18s ease-out; }
@keyframes dr-pop { from{transform:scale(.94);opacity:0} to{transform:scale(1);opacity:1} }
.dr-modal-head  { display:flex; align-items:center; gap:12px; padding:20px 22px 0; }
.dr-modal-icon  { width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:22px;flex:none; }
.dr-modal-icon.red { background:#fdecea; color:#dc3545; }
.dr-modal-head h4 { margin:0; font-size:1.1em; color:#222; }
.dr-modal-body  { padding:12px 22px 4px; color:#555; font-size:.95em; line-height:1.6; }
.dr-modal-info  { margin-top:10px; background:#f7f9fc; border:1px solid #e6edf5; border-radius:8px; padding:8px 12px; font-size:.92em; color:#333; font-weight:600; }
.dr-modal-actions { display:flex; gap:10px; padding:18px 22px 22px; }
.dr-modal-actions button { flex:1; padding:10px; border:none; border-radius:8px; font-size:1em; font-weight:600; cursor:pointer; }
.dr-modal-actions .confirm { background:#dc3545; color:#fff; }
.dr-modal-actions .confirm:hover { background:#c82333; }
.dr-modal-actions .cancel  { background:#eef0f2; color:#333; }
.dr-modal-actions .cancel:hover { background:#e2e5e8; }
</style>

<div class="dr-page">

<?php
// ── Tab navigation ────────────────────────────────────────────────────────────
$tab_sessions = new moodle_url($pageurl, ['view' => 'sessions']);
$tab_devices  = new moodle_url($pageurl, ['view' => 'devices']);
?>
<nav class="dr-tabs">
  <a href="<?php echo $tab_sessions->out(); ?>" class="dr-tab <?php echo $view === 'sessions' ? 'active' : ''; ?>">
    <?php echo get_string('tab_sessions', 'local_deviceregistration'); ?>
  </a>
  <a href="<?php echo $tab_devices->out(); ?>" class="dr-tab <?php echo $view === 'devices' ? 'active' : ''; ?>">
    <?php echo get_string('tab_devices', 'local_deviceregistration'); ?>
  </a>
</nav>

<?php if ($view === 'sessions'): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAB 1 — Active sessions / force logout
════════════════════════════════════════════════════════════════════════════ -->

  <p class="text-muted"><?php echo get_string('forcelogout_intro', 'local_deviceregistration'); ?></p>

  <form method="get" class="dr-filter">
    <input type="hidden" name="view" value="sessions">
    <input type="text" name="filter" autocomplete="off"
           placeholder="<?php echo s(get_string('forcelogout_filter_placeholder', 'local_deviceregistration')); ?>"
           value="<?php echo s($filter); ?>">
    <button type="submit"><?php echo get_string('forcelogout_filter_btn', 'local_deviceregistration'); ?></button>
    <?php if ($filter !== ''): ?>
      <a href="<?php echo (new moodle_url($pageurl, ['view'=>'sessions']))->out(); ?>">
        <?php echo get_string('forcelogout_clear', 'local_deviceregistration'); ?></a>
    <?php endif; ?>
  </form>

  <?php if (empty($loggedinusers)): ?>
    <div class="dr-empty">
      <?php echo $filter !== ''
        ? get_string('forcelogout_nomatch', 'local_deviceregistration')
        : get_string('forcelogout_none_loggedin', 'local_deviceregistration'); ?>
    </div>
  <?php else: ?>
    <p class="dr-count"><?php echo get_string('forcelogout_count', 'local_deviceregistration', count($loggedinusers)); ?></p>
    <table class="dr-table">
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
            <div class="dr-uname"><?php echo s(fullname($u)); ?></div>
            <div class="dr-umail"><?php echo s($u->email); ?> · <?php echo s($u->username); ?></div>
          </td>
          <td><span class="dr-badge"><?php echo $u->sessioncount; ?></span></td>
          <td><?php echo userdate($u->lastactive, $datefmt); ?></td>
          <td>
            <form method="post" style="margin:0">
              <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
              <input type="hidden" name="action"  value="logout_user">
              <input type="hidden" name="userid"  value="<?php echo (int)$u->id; ?>">
              <input type="hidden" name="filter"  value="<?php echo s($filter); ?>">
              <button type="button" class="btn-force" data-dr-confirm="logout"
                      data-info="<?php echo s(fullname($u) . ' — ' . $u->email); ?>">
                <?php echo get_string('forcelogout_action', 'local_deviceregistration'); ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAB 2 — Registered devices management
════════════════════════════════════════════════════════════════════════════ -->

  <p class="text-muted"><?php echo get_string('devmgr_intro', 'local_deviceregistration'); ?></p>

  <form method="get" class="dr-filter">
    <input type="hidden" name="view" value="devices">
    <input type="text" name="filter" autocomplete="off"
           placeholder="<?php echo s(get_string('forcelogout_filter_placeholder', 'local_deviceregistration')); ?>"
           value="<?php echo s($filter); ?>">
    <select name="countfilter" class="dr-select">
      <option value=""    <?php echo $countfilter === ''           ? 'selected' : ''; ?>>
        <?php echo get_string('devmgr_filter_all',       'local_deviceregistration'); ?>
      </option>
      <option value="at_limit"    <?php echo $countfilter === 'at_limit'    ? 'selected' : ''; ?>>
        <?php echo get_string('devmgr_filter_at_limit',  'local_deviceregistration'); ?>
      </option>
      <option value="under_limit" <?php echo $countfilter === 'under_limit' ? 'selected' : ''; ?>>
        <?php echo get_string('devmgr_filter_under',     'local_deviceregistration'); ?>
      </option>
      <?php for ($n = 1; $n <= max(3, $max + 1); $n++): ?>
      <option value="<?php echo $n; ?>" <?php echo $countfilter === (string)$n ? 'selected' : ''; ?>>
        <?php echo get_string('devmgr_filter_exact', 'local_deviceregistration', $n); ?>
      </option>
      <?php endfor; ?>
    </select>
    <button type="submit"><?php echo get_string('forcelogout_filter_btn', 'local_deviceregistration'); ?></button>
    <?php if ($filter !== '' || $countfilter !== ''): ?>
      <a href="<?php echo (new moodle_url($pageurl, ['view'=>'devices']))->out(); ?>">
        <?php echo get_string('forcelogout_clear', 'local_deviceregistration'); ?></a>
    <?php endif; ?>
  </form>

  <?php if ($selecteduser && $selecteddevices !== false): ?>
  <!-- ── Expanded device detail panel ───────────────────────────────────── -->
  <div class="dr-detail">
    <h4><?php echo get_string('devmgr_devices_for', 'local_deviceregistration',
            s(fullname($selecteduser) . ' — ' . $selecteduser->email)); ?></h4>

    <?php if (empty($selecteddevices)): ?>
      <p class="text-muted" style="margin:0"><?php echo get_string('nodevices', 'local_deviceregistration'); ?></p>
    <?php else: ?>
      <table class="dr-table">
        <thead>
          <tr>
            <th><?php echo get_string('device', 'local_deviceregistration'); ?></th>
            <th><?php echo get_string('lastip', 'local_deviceregistration'); ?></th>
            <th><?php echo get_string('firstseen', 'local_deviceregistration'); ?></th>
            <th><?php echo get_string('lastseen', 'local_deviceregistration'); ?></th>
            <th><?php echo get_string('actions', 'local_deviceregistration'); ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($selecteddevices as $d): ?>
          <tr>
            <td><div class="dr-ua" title="<?php echo s($d->useragent); ?>">
              <?php echo s($d->useragent ?: get_string('unknowndevice', 'local_deviceregistration')); ?>
            </div></td>
            <td><?php echo s($d->lastip ?: '—'); ?></td>
            <td><?php echo userdate($d->timecreated,   $datefmt); ?></td>
            <td><?php echo userdate($d->timelastseen,  $datefmt); ?></td>
            <td>
              <form method="post" style="margin:0">
                <input type="hidden" name="sesskey"    value="<?php echo sesskey(); ?>">
                <input type="hidden" name="action"     value="remove_device">
                <input type="hidden" name="deviceid"   value="<?php echo (int)$d->id; ?>">
                <input type="hidden" name="filter"     value="<?php echo s($filter); ?>">
                <button type="button" class="btn-revoke" data-dr-confirm="revoke"
                        data-info="<?php echo s(($d->useragent ?: get_string('unknowndevice', 'local_deviceregistration')) . ' — ' . ($d->lastip ?: '?')); ?>">
                  <?php echo get_string('devmgr_revoke', 'local_deviceregistration'); ?>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <p style="margin-top:12px;margin-bottom:0">
      <a href="<?php echo (new moodle_url($pageurl, ['view'=>'devices','filter'=>$filter,'countfilter'=>$countfilter]))->out(); ?>">
        ← <?php echo get_string('devmgr_back', 'local_deviceregistration'); ?>
      </a>
    </p>
  </div>
  <?php endif; ?>

  <!-- ── User list ───────────────────────────────────────────────────────── -->
  <?php if (empty($deviceusers)): ?>
    <div class="dr-empty">
      <?php echo $filter !== ''
        ? get_string('forcelogout_nomatch', 'local_deviceregistration')
        : get_string('devmgr_none', 'local_deviceregistration'); ?>
    </div>
  <?php else: ?>
    <p class="dr-count"><?php echo get_string('devmgr_count', 'local_deviceregistration', count($deviceusers)); ?></p>
    <table class="dr-table">
      <thead>
        <tr>
          <th><?php echo get_string('forcelogout_col_user', 'local_deviceregistration'); ?></th>
          <th><?php echo get_string('devmgr_col_devices', 'local_deviceregistration'); ?></th>
          <th><?php echo get_string('devmgr_col_limit', 'local_deviceregistration'); ?></th>
          <th><?php echo get_string('forcelogout_col_lastactive', 'local_deviceregistration'); ?></th>
          <th><?php echo get_string('actions', 'local_deviceregistration'); ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($deviceusers as $u):
        $atLimit = $max > 0 && $u->devicecount >= $max;
      ?>
        <tr>
          <td>
            <div class="dr-uname"><?php echo s(fullname($u)); ?></div>
            <div class="dr-umail"><?php echo s($u->email); ?></div>
          </td>
          <td>
            <span class="dr-badge <?php echo $atLimit ? 'warn' : ''; ?>">
              <?php echo $u->devicecount; ?>
            </span>
          </td>
          <td><?php echo $max > 0 ? $max : get_string('unlimited', 'local_deviceregistration'); ?></td>
          <td><?php echo userdate($u->lastseen, $datefmt); ?></td>
          <td>
            <a href="<?php echo (new moodle_url($pageurl, [
                  'view'        => 'devices',
                  'deviceuser'  => $u->id,
                  'filter'      => $filter,
                  'countfilter' => $countfilter,
                ]))->out(); ?>" class="btn-manage">
              <?php echo get_string('devmgr_manage', 'local_deviceregistration'); ?>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

<?php endif; ?>
</div><!-- .dr-page -->

<!-- ── Confirmation modal ─────────────────────────────────────────────────────── -->
<div class="dr-modal-overlay" id="dr-modal">
  <div class="dr-modal-box" role="dialog" aria-modal="true" aria-labelledby="dr-modal-title">
    <div class="dr-modal-head">
      <div class="dr-modal-icon red">&#9888;</div>
      <h4 id="dr-modal-title"><?php echo get_string('forcelogout_confirm_title', 'local_deviceregistration'); ?></h4>
    </div>
    <div class="dr-modal-body">
      <p id="dr-modal-msg" style="margin:0"></p>
      <div class="dr-modal-info" id="dr-modal-info"></div>
    </div>
    <div class="dr-modal-actions">
      <button type="button" class="confirm" id="dr-modal-confirm">
        <?php echo get_string('forcelogout_confirm_yes', 'local_deviceregistration'); ?>
      </button>
      <button type="button" class="cancel"  id="dr-modal-cancel">
        <?php echo get_string('cancel'); ?>
      </button>
    </div>
  </div>
</div>

<script>
(function () {
  var overlay    = document.getElementById('dr-modal');
  var msgEl      = document.getElementById('dr-modal-msg');
  var infoEl     = document.getElementById('dr-modal-info');
  var confirmBtn = document.getElementById('dr-modal-confirm');
  var cancelBtn  = document.getElementById('dr-modal-cancel');
  var pendingForm = null;

  var msgs = {
    logout: <?php echo json_encode(get_string('forcelogout_confirm_all', 'local_deviceregistration')); ?>,
    revoke: <?php echo json_encode(get_string('devmgr_confirm_revoke', 'local_deviceregistration')); ?>
  };

  function openModal(form, type, info) {
    pendingForm  = form;
    msgEl.textContent  = msgs[type] || '';
    infoEl.textContent = info || '';
    overlay.classList.add('open');
  }
  function closeModal() { overlay.classList.remove('open'); pendingForm = null; }

  document.querySelectorAll('[data-dr-confirm]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openModal(btn.closest('form'), btn.getAttribute('data-dr-confirm'), btn.getAttribute('data-info'));
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
