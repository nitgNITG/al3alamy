<?php
/**
 * local_videopay — per-module pricing hooks.
 *
 * Adds a "Pricing" section to every course module editing form so teachers
 * can mark each activity/resource as either free or set an EGP price.
 *
 * The price is stored in mdl_local_videopay_prices (cmid → price, is_free).
 * The course renderer reads this table to decide whether to show a payment
 * popup or let the student access the content freely.
 */

defined('MOODLE_INTERNAL') || die();

// ── Module edit form: add Pricing section ─────────────────────────────────

/**
 * Called from modedit.php when building the module editing form.
 * Injects a "Pricing" header + price field + free checkbox.
 *
 * @param moodleform_mod $formwrapper
 * @param MoodleQuickForm $mform
 */
function local_videopay_coursemodule_standard_elements($formwrapper, $mform): void {
    global $DB;

    // Determine if we're editing an existing CM.
    $cmid = optional_param('update', 0, PARAM_INT);

    $price   = 0.0;
    $is_free = 1;

    if ($cmid > 0) {
        $rec = $DB->get_record('local_videopay_prices', ['cmid' => $cmid]);
        if ($rec) {
            $price   = (float)$rec->price;
            $is_free = (int)$rec->is_free;
        }
    }

    // ── Section header ───────────────────────────────────────────────────
    $mform->addElement('header', 'videopay_header',
        get_string('pricing_header', 'local_videopay'));
    $mform->setExpanded('videopay_header', true);

    // ── Free / Paid toggle ───────────────────────────────────────────────
    $mform->addElement('advcheckbox', 'videopay_is_free',
        get_string('is_free', 'local_videopay'),
        get_string('is_free_label', 'local_videopay'));
    $mform->setDefault('videopay_is_free', $is_free);
    $mform->addHelpButton('videopay_is_free', 'is_free', 'local_videopay');

    // ── Price field (EGP) ────────────────────────────────────────────────
    $mform->addElement('text', 'videopay_price',
        get_string('price', 'local_videopay'),
        ['size' => 8, 'placeholder' => '0']);
    $mform->setType('videopay_price', PARAM_FLOAT);
    $mform->setDefault('videopay_price', $price);
    $mform->addHelpButton('videopay_price', 'price', 'local_videopay');

    // Disable the price input when "Free" is ticked.
    $mform->disabledIf('videopay_price', 'videopay_is_free', 'checked');

    // Price must be ≥ 0 when paid.
    $mform->addRule('videopay_price', null, 'numeric',  null, 'client');
}

// ── Module edit form: save price after CM is saved ────────────────────────

/**
 * Called from modedit.php after the module record is written.
 * Upserts the price record for this CM.
 *
 * @param stdClass $data  The submitted form data (has ->coursemodule)
 * @param stdClass $course
 * @return stdClass $data  Must be returned unchanged.
 */
function local_videopay_coursemodule_edit_post_actions($data, $course): stdClass {
    global $DB;

    $cmid    = (int)$data->coursemodule;
    $price   = isset($data->videopay_price)   ? (float)$data->videopay_price          : 0.0;
    $is_free = isset($data->videopay_is_free) ? (int)(bool)$data->videopay_is_free    : 1;

    // If ticked as free, force price to 0.
    if ($is_free) {
        $price = 0.0;
    }

    $now      = time();
    $existing = $DB->get_record('local_videopay_prices', ['cmid' => $cmid]);
    $groupid  = $existing ? (int)$existing->groupid : 0;

    // ── Auto-manage the gating group + section restriction ────────────────
    // Paid → ensure a dedicated group exists and gate the WHOLE section behind
    //        it (the video plus every supporting activity), so students must pay
    //        the video to unlock everything in that lesson.
    // Free → drop that group from the section. The now-free module may itself be
    //        a supporting activity under another paid video, so re-inherit any
    //        remaining paid-video gate in its section.
    if (!$is_free) {
        $groupid = local_videopay_ensure_group((int)$course->id, $cmid, $groupid,
            $data->name ?? ('cm' . $cmid));
        local_videopay_gate_section($cmid, (int)$course->id, $groupid);
    } else {
        if ($groupid) {
            local_videopay_ungate_section($cmid, (int)$course->id, $groupid);
        }
        local_videopay_inherit_section_gate($cmid, (int)$course->id);
    }

    if ($existing) {
        $existing->price        = $price;
        $existing->is_free      = $is_free;
        $existing->groupid      = $groupid;
        $existing->timemodified = $now;
        $DB->update_record('local_videopay_prices', $existing);
    } else {
        $DB->insert_record('local_videopay_prices', [
            'cmid'         => $cmid,
            'price'        => $price,
            'is_free'      => $is_free,
            'groupid'      => $groupid,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    return $data;
}

// ── Group automation helpers ──────────────────────────────────────────────

/**
 * Return a valid gating group id for a paid module, creating one if needed.
 *
 * @param int    $courseid
 * @param int    $cmid
 * @param int    $existinggroupid  Previously stored group id (0 if none).
 * @param string $label            Human-readable name for the group.
 * @return int   The group id to gate this module with.
 */
function local_videopay_ensure_group(int $courseid, int $cmid, int $existinggroupid, string $label): int {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/group/lib.php');

    // Reuse the stored group if it still exists in this course.
    if ($existinggroupid
        && $DB->record_exists('groups', ['id' => $existinggroupid, 'courseid' => $courseid])) {
        return $existinggroupid;
    }

    $idnumber = 'videopay-cm' . $cmid;

    // Reuse a group previously created for this cm (matched by idnumber).
    $existing = $DB->get_record('groups', ['courseid' => $courseid, 'idnumber' => $idnumber]);
    if ($existing) {
        return (int)$existing->id;
    }

    // Keep within column limits (name varchar(254); description may be short on
    // customised installs). Description is optional, so leave it empty.
    $group = (object)[
        'courseid'    => $courseid,
        'name'        => core_text::substr('Paid video: ' . $label, 0, 254),
        'idnumber'    => $idnumber,
        'description' => '',
    ];
    return (int)groups_create_group($group);
}

/**
 * Add or remove a single group condition on a module's availability, without
 * rebuilding the course cache (callers batch the rebuild). Preserves any other
 * pre-existing conditions. When $add is false and no conditions remain, the
 * availability is cleared entirely.
 *
 * @param int  $cmid
 * @param int  $groupid
 * @param bool $add  true to require the group, false to drop it.
 */
function local_videopay_module_set_group(int $cmid, int $groupid, bool $add): void {
    global $DB;

    $cm = $DB->get_record('course_modules', ['id' => $cmid], 'id, availability');
    if (!$cm) {
        return;
    }
    $tree = local_videopay_decode_availability($cm->availability);

    // Drop any existing condition for this same group (avoid duplicates).
    $tree['c'] = array_values(array_filter($tree['c'], function ($c) use ($groupid) {
        return !(isset($c['type'], $c['id']) && $c['type'] === 'group' && (int)$c['id'] === $groupid);
    }));
    if ($add) {
        $tree['c'][] = ['type' => 'group', 'id' => $groupid];
    }

    if (empty($tree['c'])) {
        $DB->set_field('course_modules', 'availability', null, ['id' => $cmid]);
        return;
    }
    // showc = true → the locked item stays VISIBLE (greyed) so the student can
    // see it, see the price, and click through to pay. false would hide it.
    $tree['showc'] = array_fill(0, count($tree['c']), true);
    $DB->set_field('course_modules', 'availability', json_encode($tree), ['id' => $cmid]);
}

/**
 * Ensure the module's availability requires membership of $groupid.
 * Preserves any pre-existing conditions; rebuilds the course cache.
 */
function local_videopay_apply_group_restriction(int $cmid, int $courseid, int $groupid): void {
    local_videopay_module_set_group($cmid, $groupid, true);
    rebuild_course_cache($courseid, true);
}

/**
 * Remove the group condition for $groupid from the module's availability.
 * If no conditions remain, availability is cleared entirely.
 */
function local_videopay_clear_group_restriction(int $cmid, int $courseid, int $groupid): void {
    local_videopay_module_set_group($cmid, $groupid, false);
    rebuild_course_cache($courseid, true);
}

/**
 * Return the course_module ids that share a section with $cmid (excluding it).
 *
 * @param int $cmid
 * @return int[]
 */
function local_videopay_section_siblings(int $cmid): array {
    global $DB;
    $cm = $DB->get_record('course_modules', ['id' => $cmid], 'id, section');
    if (!$cm) {
        return [];
    }
    $ids = $DB->get_fieldset_select('course_modules', 'id',
        'section = :s AND id <> :cmid', ['s' => $cm->section, 'cmid' => $cmid]);
    return array_map('intval', $ids);
}

/**
 * Whether a course module is a PAID videopay item (gates its own section).
 */
function local_videopay_is_paid_module(int $cmid): bool {
    global $DB;
    $rec = $DB->get_record('local_videopay_prices', ['cmid' => $cmid], 'is_free');
    return $rec && (int)$rec->is_free === 0;
}

/**
 * Gate an entire section behind a paid video: require $groupid on the video and
 * on every supporting activity in the same section. Other paid videos are left
 * alone (they keep their own gate). Rebuilds the course cache once.
 */
function local_videopay_gate_section(int $videocmid, int $courseid, int $groupid): void {
    local_videopay_module_set_group($videocmid, $groupid, true);
    foreach (local_videopay_section_siblings($videocmid) as $sid) {
        if (local_videopay_is_paid_module($sid)) {
            continue; // Another paid video keeps its own gate.
        }
        local_videopay_module_set_group($sid, $groupid, true);
    }
    rebuild_course_cache($courseid, true);
}

/**
 * Remove $groupid gating from the video and every supporting activity in its
 * section (used when a video is switched back to free). Rebuilds once.
 */
function local_videopay_ungate_section(int $videocmid, int $courseid, int $groupid): void {
    local_videopay_module_set_group($videocmid, $groupid, false);
    foreach (local_videopay_section_siblings($videocmid) as $sid) {
        if (local_videopay_is_paid_module($sid)) {
            continue; // Don't disturb another paid video's own gate.
        }
        local_videopay_module_set_group($sid, $groupid, false);
    }
    rebuild_course_cache($courseid, true);
}

/**
 * For a (non-paid) module, inherit the gate of any paid video already present in
 * its section, so a freshly added or newly-freed supporting activity is locked
 * until the section's video is paid for. Rebuilds once if anything changed.
 */
function local_videopay_inherit_section_gate(int $cmid, int $courseid): void {
    global $DB;

    if (local_videopay_is_paid_module($cmid)) {
        return; // Paid videos gate the section; they don't inherit.
    }
    $changed = false;
    foreach (local_videopay_section_siblings($cmid) as $sid) {
        $rec = $DB->get_record('local_videopay_prices', ['cmid' => $sid], 'groupid, is_free');
        if ($rec && (int)$rec->is_free === 0 && (int)$rec->groupid > 0) {
            local_videopay_module_set_group($cmid, (int)$rec->groupid, true);
            $changed = true;
        }
    }
    if ($changed) {
        rebuild_course_cache($courseid, true);
    }
}

/**
 * Decode an availability JSON string into a normalised tree with keys op/c/showc.
 */
function local_videopay_decode_availability(?string $json): array {
    $tree = !empty($json) ? json_decode($json, true) : null;
    if (!is_array($tree)) {
        $tree = [];
    }
    $tree += ['op' => '&', 'c' => [], 'showc' => []];
    if (!is_array($tree['c'])) {
        $tree['c'] = [];
    }
    return $tree;
}

// ── Helper: fetch price for a CM (used by renderer) ──────────────────────

/**
 * Return [price, is_free] for a given course module ID.
 * Defaults to free (price=0, is_free=1) if no record exists.
 *
 * @param int $cmid
 * @return array  [float $price, bool $is_free]
 */
function local_videopay_get_price(int $cmid): array {
    global $DB;
    $rec = $DB->get_record('local_videopay_prices', ['cmid' => $cmid]);
    if (!$rec) {
        return [0.0, true];
    }
    return [(float)$rec->price, (bool)$rec->is_free];
}

// ── Front-end: price badges + Buy buttons + Kashier popup ─────────────────

/**
 * Injected before the footer on every page. On a course view page it enhances
 * each priced video module: shows a price / Free badge, and — for locked paid
 * lessons the current user hasn't unlocked — replaces Moodle's restriction text
 * with a "Buy" button that opens a payment popup (code / wallet / Kashier card).
 *
 * Format-agnostic: works for topics, multitopic, or any course format because it
 * operates on the rendered DOM (#module-{cmid}) rather than a format renderer.
 *
 * @return string HTML/JS to append near the footer.
 */
function local_videopay_before_footer(): string {
    global $PAGE, $DB, $USER, $CFG, $COURSE;

    // Course view pages only, real courses only, and never in editing mode.
    if (strpos((string)$PAGE->pagetype, 'course-view') !== 0) {
        return '';
    }
    $courseid = (int)$COURSE->id;
    if ($courseid <= 1 || $PAGE->user_is_editing()) {
        return '';
    }

    $records = $DB->get_records_sql(
        "SELECT p.cmid, p.price, p.is_free, p.groupid
           FROM {local_videopay_prices} p
           JOIN {course_modules} cm ON cm.id = p.cmid
           JOIN {modules} m ON m.id = cm.module
          WHERE cm.course = :courseid AND m.name = 'resource2'",
        ['courseid' => $courseid]);
    if (!$records) {
        return '';
    }

    $modinfo = get_fast_modinfo($courseid);
    $priced = [];               // cmid => true, for every priced video module.
    foreach ($records as $r) {
        $priced[(int)$r->cmid] = true;
    }

    $items = [];
    $lockedsections = [];       // sectionnum => true, sections with a locked paid video.
    foreach ($records as $r) {
        try {
            $cminfo = $modinfo->get_cm((int)$r->cmid);
        } catch (Exception $e) {
            continue; // module deleted / not in modinfo.
        }
        $paid = ((int)$r->is_free === 0);
        // "unlocked" = availability conditions met for this user (covers group
        // membership and accessallgroups for staff).
        $unlocked = !$paid || (bool)$cminfo->available;
        $items[] = [
            'cmid'     => (int)$r->cmid,
            'price'    => (int)$r->price,
            'free'     => !$paid,
            'groupid'  => (int)$r->groupid,
            'unlocked' => $unlocked,
        ];
        if ($paid && !$unlocked) {
            $lockedsections[(int)$cminfo->sectionnum] = true;
        }
    }
    if (!$items) {
        return '';
    }

    // Supporting activities that are locked because their section's video isn't
    // paid yet (Phase 2 gating). We surface a friendly hint instead of Moodle's
    // raw "Not available unless…" text on these.
    $gated = [];
    foreach (array_keys($lockedsections) as $sn) {
        if (empty($modinfo->sections[$sn])) {
            continue;
        }
        foreach ($modinfo->sections[$sn] as $scmid) {
            $scmid = (int)$scmid;
            if (isset($priced[$scmid])) {
                continue; // The videos themselves are handled as items above.
            }
            $scminfo = $modinfo->get_cm($scmid);
            if ($scminfo->available || !$scminfo->is_visible_on_course_page()) {
                continue; // Already unlocked for this user, or not shown.
            }
            $gated[] = $scmid;
        }
    }

    $config = json_encode([
        'wwwroot'  => $CFG->wwwroot,
        'courseid' => $courseid,
        'userid'   => (int)$USER->id,
        'items'    => $items,
        'gated'    => array_values(array_unique($gated)),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $js = <<<JS
<script>
(function () {
  var CFG = {$config};
  function money(n){ return n + ' ج.م'; }

  function addBadge(target, text, bg){
    if (!target || target.querySelector('.vp-badge')) return;
    var b = document.createElement('span');
    b.className = 'vp-badge';
    b.textContent = text;
    b.style.cssText = 'display:inline-block;margin-inline-start:8px;padding:1px 8px;'
      + 'border-radius:10px;font-size:12px;font-weight:600;color:#fff;vertical-align:middle;background:' + bg + ';';
    target.appendChild(b);
  }

  // ── Build the payment modal once ────────────────────────────────────────
  var modal = document.createElement('div');
  modal.id = 'vp-modal';
  modal.style.cssText = 'display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.5);';
  modal.innerHTML =
    '<div style="background:#fff;max-width:420px;margin:12vh auto;padding:24px;border-radius:12px;position:relative;text-align:center;font-family:inherit;">'
    + '<span id="vp-close" style="position:absolute;top:10px;inset-inline-end:16px;font-size:26px;cursor:pointer;color:#999;">&times;</span>'
    + '<h4 style="margin:0 0 4px;color:#00126C;">افتح هذا الدرس / Unlock this lesson</h4>'
    + '<div id="vp-price" style="font-size:22px;font-weight:700;color:#C9A227;margin-bottom:14px;"></div>'
    + '<div id="vp-msg" style="display:none;margin-bottom:10px;padding:8px;border-radius:6px;font-size:14px;"></div>'
    + '<form id="vp-codeform" style="margin-bottom:10px;">'
    +   '<input id="vp-code" type="text" placeholder="أدخل الكود / Enter code" '
    +     'style="width:100%;padding:8px;text-align:center;border:1px solid #ccc;border-radius:6px;margin-bottom:8px;">'
    +   '<button type="submit" style="width:100%;padding:9px;border:0;border-radius:6px;background:#6c757d;color:#fff;font-weight:600;cursor:pointer;">إرسال الكود / Submit code</button>'
    + '</form>'
    + '<button id="vp-wallet" type="button" style="width:100%;padding:9px;border:1px solid #00126C;border-radius:6px;background:#fff;color:#00126C;font-weight:600;cursor:pointer;margin-bottom:8px;">💰 الدفع بالمحفظة / Pay with Wallet</button>'
    + '<a id="vp-card" href="#" style="display:block;width:100%;padding:10px;border-radius:6px;background:#00126C;color:#fff;font-weight:600;text-decoration:none;">💳 ادفع بالكارت / Pay by Card (Kashier)</a>'
    + '</div>';
  document.body.appendChild(modal);

  var current = null;
  var priceEl = document.getElementById('vp-price');
  var msgEl   = document.getElementById('vp-msg');
  var cardEl  = document.getElementById('vp-card');

  function showMsg(text, ok){
    msgEl.textContent = text;
    msgEl.style.display = 'block';
    msgEl.style.background = ok ? '#d4edda' : '#f8d7da';
    msgEl.style.color = ok ? '#155724' : '#721c24';
  }
  function openModal(item){
    current = item;
    msgEl.style.display = 'none';
    document.getElementById('vp-code').value = '';
    priceEl.textContent = money(item.price);
    cardEl.href = CFG.wwwroot + '/kashier/pay.php?cmid=' + item.cmid
      + '&groupid=' + item.groupid + '&courseid=' + CFG.courseid + '&amount=' + item.price;
    modal.style.display = 'block';
  }
  function closeModal(){ modal.style.display = 'none'; }

  document.getElementById('vp-close').addEventListener('click', closeModal);
  modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });

  function post(url, params, done){
    var xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function(){
      if (xhr.readyState === 4){
        var res = null;
        try { res = JSON.parse(xhr.responseText); } catch(e){}
        done(res);
      }
    };
    var body = Object.keys(params).map(function(k){
      return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
    }).join('&');
    xhr.send(body);
  }

  document.getElementById('vp-codeform').addEventListener('submit', function(e){
    e.preventDefault();
    if (!current) return;
    post(CFG.wwwroot + '/checkcode.php', {
      code: document.getElementById('vp-code').value,
      groupid: current.groupid, userid: CFG.userid, courseid: CFG.courseid, clickedAct: 'mod'
    }, function(res){
      if (res && res.status === 'success'){ showMsg(res.message || 'تم / Done', true); setTimeout(function(){ location.reload(); }, 1200); }
      else { showMsg((res && res.message) || 'خطأ / Error', false); }
    });
  });

  document.getElementById('vp-wallet').addEventListener('click', function(){
    if (!current) return;
    post(CFG.wwwroot + '/paywallet.php', {
      groupid: current.groupid, userid: CFG.userid, courseid: CFG.courseid, clickedAct: 'mod'
    }, function(res){
      if (res && res.status === 'success'){ showMsg(res.message || 'تم / Done', true); setTimeout(function(){ location.reload(); }, 1200); }
      else { showMsg((res && res.message) || 'خطأ / Error', false); }
    });
  });

  // ── Enhance each priced module ──────────────────────────────────────────
  CFG.items.forEach(function(item){
    var el = document.getElementById('module-' + item.cmid);
    if (!el) return;
    var title = el.querySelector('.activityname') || el.querySelector('.instancename')
      || el.querySelector('.activityinstance') || el;

    if (item.free){
      addBadge(title, 'Free / مجاني', '#1a9c5b');
      return;
    }
    addBadge(title, money(item.price), '#00126C');
    if (item.unlocked) return; // already purchased / staff — leave normal link.

    // Hide Moodle's default "Not available unless..." restriction text.
    var info = el.querySelector('.availabilityinfo');
    if (info) info.style.display = 'none';

    var buy = document.createElement('button');
    buy.type = 'button';
    buy.className = 'vp-buy-btn';
    buy.textContent = 'شراء / Buy';
    buy.style.cssText = 'margin-inline-start:10px;padding:3px 14px;border:0;border-radius:8px;'
      + 'background:#C9A227;color:#fff;font-weight:700;cursor:pointer;font-size:13px;vertical-align:middle;';
    buy.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); openModal(item); });
    title.appendChild(buy);

    // Clicking the lesson name itself also opens the popup.
    var clickable = el.querySelector('.activityinstance') || title;
    clickable.style.cursor = 'pointer';
    clickable.addEventListener('click', function(e){ e.preventDefault(); openModal(item); });
  });

  // ── Supporting activities locked behind their section's video ───────────────
  // Replace Moodle's raw "Not available unless…" text with a friendly hint.
  (CFG.gated || []).forEach(function(cmid){
    var el = document.getElementById('module-' + cmid);
    if (!el) return;
    var info = el.querySelector('.availabilityinfo');
    if (info) info.style.display = 'none';
    var title = el.querySelector('.activityname') || el.querySelector('.instancename')
      || el.querySelector('.activityinstance') || el;
    if (title.querySelector('.vp-lock-hint')) return;
    var hint = document.createElement('span');
    hint.className = 'vp-lock-hint';
    hint.textContent = '🔒 افتح فيديو الدرس أولاً / Unlock by buying the lesson video';
    hint.style.cssText = 'display:inline-block;margin-inline-start:8px;padding:1px 8px;'
      + 'border-radius:10px;font-size:12px;font-weight:600;color:#fff;vertical-align:middle;background:#8a6d1f;';
    title.appendChild(hint);
  });
})();
</script>
JS;

    return $js;
}
