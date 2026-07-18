<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/adminlib.php');

// ── Permissions (before any page setup) ──────────────────────────────────────
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

global $DB;

$pageurl     = new moodle_url('/local/deviceregistration/admin_force_logout.php');
$action      = optional_param('action',      '',  PARAM_ALPHA);
$userid      = optional_param('userid',      0,   PARAM_INT);
$deviceid    = optional_param('deviceid',    0,   PARAM_INT);
$filter      = trim(optional_param('filter', '',  PARAM_RAW));
$countfilter = optional_param('countfilter', '',  PARAM_RAW);
$deviceuser  = optional_param('deviceuser',  0,   PARAM_INT);
$view        = optional_param('view',        'sessions', PARAM_ALPHA);

// ── Helper ────────────────────────────────────────────────────────────────────
function _dr_kill_all_sessions(int $uid): int {
    global $DB;
    $count = (int) $DB->count_records('sessions', ['userid' => $uid]);
    try { \core\session\manager::kill_user_sessions($uid); } catch (Throwable $e) {}
    try { $DB->delete_records('sessions', ['userid' => $uid]); } catch (Throwable $e) {}
    return $count;
}

// ── ACTIONS — handled before admin_externalpage_setup so no URL redirect ──────
if ($action === 'logout_user' && $userid) {
    require_sesskey();
    $target = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', IGNORE_MISSING);
    if ($target) {
        $killed = _dr_kill_all_sessions($userid);
        redirect(
            new moodle_url($pageurl, ['view' => 'sessions', 'filter' => $filter]),
            get_string('forcelogout_done', 'local_deviceregistration',
                (object)['name' => fullname($target), 'count' => $killed]),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    redirect(new moodle_url($pageurl, ['view' => 'sessions']));
}

if ($action === 'remove_device' && $deviceid) {
    require_sesskey();
    $device = $DB->get_record('local_devreg_device', ['id' => $deviceid], '*', IGNORE_MISSING);
    if ($device) {
        $owner = $DB->get_record('user', ['id' => $device->userid, 'deleted' => 0],
            'id,firstname,lastname,email', IGNORE_MISSING);
        $DB->delete_records('local_devreg_device', ['id' => $deviceid]);
        redirect(
            new moodle_url($pageurl, ['view' => 'devices', 'deviceuser' => $device->userid,
                'filter' => $filter, 'countfilter' => $countfilter]),
            get_string('device_revoked', 'local_deviceregistration',
                (object)['name' => $owner ? fullname($owner) : '?']),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    redirect(new moodle_url($pageurl, ['view' => 'devices']));
}

// ── Page setup (only reached for display, never for actions) ──────────────────
admin_externalpage_setup('local_deviceregistration_forcelogout');

// ── Data: sessions tab ────────────────────────────────────────────────────────
$loggedinusers = [];
if ($view === 'sessions') {
    $rows = $DB->get_records_sql(
        "SELECT s.userid, COUNT(s.id) AS sessioncount, MAX(s.timemodified) AS lastactive
           FROM {sessions} s WHERE s.userid > 0
        GROUP BY s.userid ORDER BY MAX(s.timemodified) DESC");
    if ($rows) {
        $nf = 'id,firstname,lastname,email,username,deleted,suspended,'
            . 'firstnamephonetic,lastnamephonetic,middlename,alternatename';
        $users = $DB->get_records_list('user', 'id', array_keys($rows), '', $nf);
        foreach ($rows as $uid => $r) {
            if (empty($users[$uid]) || $users[$uid]->deleted) continue;
            $u = $users[$uid];
            if ($filter !== '') {
                $hay = core_text::strtolower(fullname($u).' '.$u->email.' '.$u->username);
                if (strpos($hay, core_text::strtolower($filter)) === false) continue;
            }
            $u->sessioncount = (int)$r->sessioncount;
            $u->lastactive   = (int)$r->lastactive;
            $loggedinusers[] = $u;
        }
    }
}

// ── Data: devices tab ─────────────────────────────────────────────────────────
$deviceusers = []; $selecteddevices = []; $selecteduser = null;
$max = local_deviceregistration_max_devices();

if ($view === 'devices') {
    $devrows = $DB->get_records_sql(
        "SELECT d.userid, COUNT(d.id) AS devicecount, MAX(d.timelastseen) AS lastseen
           FROM {local_devreg_device} d GROUP BY d.userid ORDER BY MAX(d.timelastseen) DESC");
    if ($devrows) {
        $nf = 'id,firstname,lastname,email,username,deleted,'
            . 'firstnamephonetic,lastnamephonetic,middlename,alternatename';
        $users = $DB->get_records_list('user', 'id', array_keys($devrows), '', $nf);
        foreach ($devrows as $uid => $r) {
            if (empty($users[$uid]) || $users[$uid]->deleted) continue;
            $u = $users[$uid];
            $u->devicecount = (int)$r->devicecount;
            $u->lastseen    = (int)$r->lastseen;
            if ($filter !== '') {
                $hay = core_text::strtolower(fullname($u).' '.$u->email.' '.$u->username);
                if (strpos($hay, core_text::strtolower($filter)) === false) continue;
            }
            if ($countfilter !== '') {
                if ($countfilter === 'at_limit')    { if (!($max>0 && $u->devicecount>=$max)) continue; }
                elseif ($countfilter === 'under_limit') { if (!($max<=0 || $u->devicecount<$max)) continue; }
                elseif (is_numeric($countfilter))   { if ($u->devicecount!==(int)$countfilter) continue; }
            }
            $deviceusers[] = $u;
        }
    }
    if ($deviceuser > 0) {
        $selecteduser    = $DB->get_record('user', ['id'=>$deviceuser,'deleted'=>0],
            'id,firstname,lastname,email,username', IGNORE_MISSING);
        $selecteddevices = $DB->get_records('local_devreg_device',
            ['userid'=>$deviceuser], 'timelastseen DESC');
    }
}

$datefmt = get_string('strftimedatetimeshort', 'langconfig');
$sk      = sesskey();

echo $OUTPUT->header();
?>
<style>
.dr-page  { max-width:900px; }
.dr-tabs  { display:flex;gap:4px;margin-bottom:24px;border-bottom:2px solid #dee2e6; }
.dr-tab   { padding:9px 22px;font-weight:600;font-size:.93em;color:#555;text-decoration:none;
            border:1px solid transparent;border-bottom:none;border-radius:6px 6px 0 0; }
.dr-tab:hover  { color:#2d6a9f;background:#f0f4f9; }
.dr-tab.active { color:#2d6a9f;background:#fff;border-color:#dee2e6;margin-bottom:-2px; }
.dr-filter { display:flex;gap:8px;margin-bottom:18px; }
.dr-filter input[type=text] { flex:1;padding:8px 12px;border:1px solid #ced4da;border-radius:4px; }
.dr-filter button { padding:8px 18px;background:#2d6a9f;color:#fff;border:none;border-radius:4px;cursor:pointer; }
.dr-filter a.clear { padding:8px 14px;color:#2d6a9f;text-decoration:none;align-self:center; }
.dr-select { padding:8px 10px;border:1px solid #ced4da;border-radius:4px;background:#fff;font-size:.92em; }
.dr-count  { color:#555;font-size:.9em;margin-bottom:12px; }
.dr-table  { width:100%;border-collapse:collapse;font-size:.92em; }
.dr-table th,.dr-table td { padding:9px 12px;border:1px solid #dee2e6;text-align:start;vertical-align:middle; }
.dr-table thead th { background:#2d6a9f;color:#fff;font-weight:600; }
.dr-table tbody tr:nth-child(even) { background:#f8f9fa; }
.dr-uname { font-weight:700;color:#1a1a1a; }
.dr-umail { color:#888;font-size:.9em; }
.dr-badge { display:inline-block;background:#eef3f9;color:#2d6a9f;border-radius:10px;padding:2px 10px;font-size:.85em;font-weight:700; }
.dr-badge.warn { background:#fff3cd;color:#856404; }
.dr-empty { text-align:center;color:#888;padding:40px 20px;font-size:1.05em; }
.btn-force  { display:inline-block;background:#dc3545;color:#fff !important;border-radius:5px;padding:6px 16px;font-size:.86em;font-weight:600;text-decoration:none !important; }
.btn-force:hover  { background:#c82333;color:#fff !important; }
.btn-manage { display:inline-block;background:#2d6a9f;color:#fff !important;border-radius:5px;padding:6px 14px;font-size:.86em;font-weight:600;text-decoration:none !important; }
.btn-manage:hover { background:#245580;color:#fff !important; }
.btn-revoke { display:inline-block;background:#fff;color:#dc3545 !important;border:1px solid #dc3545;border-radius:5px;padding:5px 14px;font-size:.85em;font-weight:600;text-decoration:none !important; }
.btn-revoke:hover { background:#dc3545;color:#fff !important; }
.dr-detail { background:#f7f9fc;border:1px solid #d0dce8;border-radius:8px;padding:18px 20px;margin-bottom:24px; }
.dr-detail h4 { margin:0 0 12px;color:#2d6a9f;font-size:1em; }
.dr-ua { font-size:.82em;color:#555;max-width:300px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
</style>

<div class="dr-page">
<nav class="dr-tabs">
  <a href="<?php echo (new moodle_url($pageurl,['view'=>'sessions']))->out(); ?>"
     class="dr-tab <?php echo $view==='sessions'?'active':''; ?>">
    <?php echo get_string('tab_sessions','local_deviceregistration'); ?>
  </a>
  <a href="<?php echo (new moodle_url($pageurl,['view'=>'devices']))->out(); ?>"
     class="dr-tab <?php echo $view==='devices'?'active':''; ?>">
    <?php echo get_string('tab_devices','local_deviceregistration'); ?>
  </a>
</nav>

<?php if ($view === 'sessions'): ?>
  <p class="text-muted"><?php echo get_string('forcelogout_intro','local_deviceregistration'); ?></p>
  <form method="get" class="dr-filter">
    <input type="hidden" name="view" value="sessions">
    <input type="text" name="filter" autocomplete="off"
           placeholder="<?php echo s(get_string('forcelogout_filter_placeholder','local_deviceregistration')); ?>"
           value="<?php echo s($filter); ?>">
    <button type="submit"><?php echo get_string('forcelogout_filter_btn','local_deviceregistration'); ?></button>
    <?php if ($filter!==''): ?>
      <a href="<?php echo (new moodle_url($pageurl,['view'=>'sessions']))->out(); ?>" class="clear">
        <?php echo get_string('forcelogout_clear','local_deviceregistration'); ?></a>
    <?php endif; ?>
  </form>

  <?php if (empty($loggedinusers)): ?>
    <div class="dr-empty"><?php echo $filter!==''
      ? get_string('forcelogout_nomatch','local_deviceregistration')
      : get_string('forcelogout_none_loggedin','local_deviceregistration'); ?></div>
  <?php else: ?>
    <p class="dr-count"><?php echo get_string('forcelogout_count','local_deviceregistration',count($loggedinusers)); ?></p>
    <table class="dr-table">
      <thead><tr>
        <th><?php echo get_string('forcelogout_col_user','local_deviceregistration'); ?></th>
        <th><?php echo get_string('forcelogout_col_sessions','local_deviceregistration'); ?></th>
        <th><?php echo get_string('forcelogout_col_lastactive','local_deviceregistration'); ?></th>
        <th><?php echo get_string('actions','local_deviceregistration'); ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($loggedinusers as $u): ?>
        <tr>
          <td>
            <div class="dr-uname"><?php echo s(fullname($u)); ?></div>
            <div class="dr-umail"><?php echo s($u->email); ?> · <?php echo s($u->username); ?></div>
          </td>
          <td><span class="dr-badge"><?php echo $u->sessioncount; ?></span></td>
          <td><?php echo userdate($u->lastactive,$datefmt); ?></td>
          <td>
            <button type="button" class="btn-force"
                    data-action="logout_user"
                    data-userid="<?php echo (int)$u->id; ?>"
                    data-sesskey="<?php echo s($sk); ?>"
                    data-filter="<?php echo s($filter); ?>">
              <?php echo get_string('forcelogout_action','local_deviceregistration'); ?>
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

<?php else: ?>
  <p class="text-muted"><?php echo get_string('devmgr_intro','local_deviceregistration'); ?></p>
  <form method="get" class="dr-filter">
    <input type="hidden" name="view" value="devices">
    <input type="text" name="filter" autocomplete="off"
           placeholder="<?php echo s(get_string('forcelogout_filter_placeholder','local_deviceregistration')); ?>"
           value="<?php echo s($filter); ?>">
    <select name="countfilter" class="dr-select">
      <option value=""><?php echo get_string('devmgr_filter_all','local_deviceregistration'); ?></option>
      <option value="at_limit"    <?php echo $countfilter==='at_limit'   ?'selected':''; ?>><?php echo get_string('devmgr_filter_at_limit','local_deviceregistration'); ?></option>
      <option value="under_limit" <?php echo $countfilter==='under_limit'?'selected':''; ?>><?php echo get_string('devmgr_filter_under','local_deviceregistration'); ?></option>
      <?php for($n=1;$n<=max(3,$max+1);$n++): ?>
      <option value="<?php echo $n; ?>" <?php echo $countfilter===(string)$n?'selected':''; ?>><?php echo get_string('devmgr_filter_exact','local_deviceregistration',$n); ?></option>
      <?php endfor; ?>
    </select>
    <button type="submit"><?php echo get_string('forcelogout_filter_btn','local_deviceregistration'); ?></button>
    <?php if ($filter!==''||$countfilter!==''): ?>
      <a href="<?php echo (new moodle_url($pageurl,['view'=>'devices']))->out(); ?>" class="clear">
        <?php echo get_string('forcelogout_clear','local_deviceregistration'); ?></a>
    <?php endif; ?>
  </form>

  <?php if ($selecteduser): ?>
  <div class="dr-detail">
    <h4><?php echo get_string('devmgr_devices_for','local_deviceregistration',
        s(fullname($selecteduser).' — '.$selecteduser->email)); ?></h4>
    <?php if (empty($selecteddevices)): ?>
      <p class="text-muted" style="margin:0"><?php echo get_string('nodevices','local_deviceregistration'); ?></p>
    <?php else: ?>
      <table class="dr-table">
        <thead><tr>
          <th><?php echo get_string('device','local_deviceregistration'); ?></th>
          <th><?php echo get_string('lastip','local_deviceregistration'); ?></th>
          <th><?php echo get_string('firstseen','local_deviceregistration'); ?></th>
          <th><?php echo get_string('lastseen','local_deviceregistration'); ?></th>
          <th><?php echo get_string('actions','local_deviceregistration'); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($selecteddevices as $d):
            $label = $d->useragent ?: get_string('unknowndevice','local_deviceregistration'); ?>
          <tr>
            <td><div class="dr-ua" title="<?php echo s($d->useragent); ?>"><?php echo s($label); ?></div></td>
            <td><?php echo s($d->lastip?:'—'); ?></td>
            <td><?php echo userdate($d->timecreated,$datefmt); ?></td>
            <td><?php echo userdate($d->timelastseen,$datefmt); ?></td>
            <td>
              <button type="button" class="btn-revoke"
                      data-action="remove_device"
                      data-deviceid="<?php echo (int)$d->id; ?>"
                      data-sesskey="<?php echo s($sk); ?>"
                      data-filter="<?php echo s($filter); ?>"
                      data-countfilter="<?php echo s($countfilter); ?>">
                <?php echo get_string('devmgr_revoke','local_deviceregistration'); ?>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    <p style="margin-top:12px;margin-bottom:0">
      <a href="<?php echo (new moodle_url($pageurl,['view'=>'devices','filter'=>$filter,'countfilter'=>$countfilter]))->out(); ?>">
        ← <?php echo get_string('devmgr_back','local_deviceregistration'); ?>
      </a>
    </p>
  </div>
  <?php endif; ?>

  <?php if (empty($deviceusers)): ?>
    <div class="dr-empty"><?php echo ($filter!==''||$countfilter!=='')
      ? get_string('forcelogout_nomatch','local_deviceregistration')
      : get_string('devmgr_none','local_deviceregistration'); ?></div>
  <?php else: ?>
    <p class="dr-count"><?php echo get_string('devmgr_count','local_deviceregistration',count($deviceusers)); ?></p>
    <table class="dr-table">
      <thead><tr>
        <th><?php echo get_string('forcelogout_col_user','local_deviceregistration'); ?></th>
        <th><?php echo get_string('devmgr_col_devices','local_deviceregistration'); ?></th>
        <th><?php echo get_string('devmgr_col_limit','local_deviceregistration'); ?></th>
        <th><?php echo get_string('forcelogout_col_lastactive','local_deviceregistration'); ?></th>
        <th><?php echo get_string('actions','local_deviceregistration'); ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($deviceusers as $u): $atLimit=$max>0&&$u->devicecount>=$max; ?>
        <tr>
          <td>
            <div class="dr-uname"><?php echo s(fullname($u)); ?></div>
            <div class="dr-umail"><?php echo s($u->email); ?></div>
          </td>
          <td><span class="dr-badge <?php echo $atLimit?'warn':''; ?>"><?php echo $u->devicecount; ?></span></td>
          <td><?php echo $max>0?$max:get_string('unlimited','local_deviceregistration'); ?></td>
          <td><?php echo userdate($u->lastseen,$datefmt); ?></td>
          <td>
            <a href="<?php echo (new moodle_url($pageurl,[
                'view'=>'devices','deviceuser'=>$u->id,
                'filter'=>$filter,'countfilter'=>$countfilter
              ]))->out(); ?>" class="btn-manage">
              <?php echo get_string('devmgr_manage','local_deviceregistration'); ?>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
<?php endif; ?>
</div>

<script>
(function(){
  var base = <?php echo json_encode($pageurl->out(false)); ?>;

  function drGo(params) {
    // Build URL manually and navigate via a fresh form — bypasses every
    // Moodle AJAX/link/form interceptor because the form is created
    // programmatically and submitted immediately.
    var f = document.createElement('form');
    f.method = 'GET';
    f.action = base;
    Object.keys(params).forEach(function(k){
      var i = document.createElement('input');
      i.type = 'hidden'; i.name = k; i.value = params[k];
      f.appendChild(i);
    });
    document.body.appendChild(f);
    f.submit();
  }

  document.addEventListener('click', function(e){
    var btn = e.target.closest('button[data-action]');
    if (!btn) return;
    e.preventDefault();
    e.stopImmediatePropagation();

    var act = btn.getAttribute('data-action');

    if (act === 'logout_user') {
      drGo({
        action:  'logout_user',
        userid:  btn.getAttribute('data-userid'),
        sesskey: btn.getAttribute('data-sesskey'),
        filter:  btn.getAttribute('data-filter') || '',
        view:    'sessions'
      });
    } else if (act === 'remove_device') {
      drGo({
        action:      'remove_device',
        deviceid:    btn.getAttribute('data-deviceid'),
        sesskey:     btn.getAttribute('data-sesskey'),
        filter:      btn.getAttribute('data-filter') || '',
        countfilter: btn.getAttribute('data-countfilter') || '',
        view:        'devices'
      });
    }
  }, true); // capture phase — runs before ANY Moodle handler
})();
</script>

<?php echo $OUTPUT->footer();
