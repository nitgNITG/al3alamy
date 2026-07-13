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

    // ── Auto-manage the gating group + module restriction ─────────────────
    // Paid → ensure a dedicated group exists and require it on the module.
    // Free → drop that restriction so the content opens directly.
    if (!$is_free) {
        $groupid = local_videopay_ensure_group((int)$course->id, $cmid, $groupid,
            $data->name ?? ('cm' . $cmid));
        local_videopay_apply_group_restriction($cmid, (int)$course->id, $groupid);
    } else if ($groupid) {
        local_videopay_clear_group_restriction($cmid, (int)$course->id, $groupid);
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
 * Ensure the module's availability requires membership of $groupid.
 * Preserves any pre-existing conditions; rebuilds the course cache.
 */
function local_videopay_apply_group_restriction(int $cmid, int $courseid, int $groupid): void {
    global $DB;

    $cm = $DB->get_record('course_modules', ['id' => $cmid], 'id, availability', MUST_EXIST);
    $tree = local_videopay_decode_availability($cm->availability);

    // Drop any existing group condition for this same group, then add it fresh.
    $tree['c'] = array_values(array_filter($tree['c'], function ($c) use ($groupid) {
        return !(isset($c['type'], $c['id']) && $c['type'] === 'group' && (int)$c['id'] === $groupid);
    }));
    $tree['c'][] = ['type' => 'group', 'id' => $groupid];
    // showc = true → the locked lesson stays VISIBLE (greyed) so the student can
    // see it, see the price, and click through to pay. false would hide it entirely.
    $tree['showc'] = array_fill(0, count($tree['c']), true);

    $DB->set_field('course_modules', 'availability', json_encode($tree), ['id' => $cmid]);
    rebuild_course_cache($courseid, true);
}

/**
 * Remove the group condition for $groupid from the module's availability.
 * If no conditions remain, availability is cleared entirely.
 */
function local_videopay_clear_group_restriction(int $cmid, int $courseid, int $groupid): void {
    global $DB;

    $cm = $DB->get_record('course_modules', ['id' => $cmid], 'id, availability', MUST_EXIST);
    if (empty($cm->availability)) {
        return;
    }
    $tree = local_videopay_decode_availability($cm->availability);
    $tree['c'] = array_values(array_filter($tree['c'], function ($c) use ($groupid) {
        return !(isset($c['type'], $c['id']) && $c['type'] === 'group' && (int)$c['id'] === $groupid);
    }));

    if (empty($tree['c'])) {
        $DB->set_field('course_modules', 'availability', null, ['id' => $cmid]);
    } else {
        $tree['showc'] = array_fill(0, count($tree['c']), true);
        $DB->set_field('course_modules', 'availability', json_encode($tree), ['id' => $cmid]);
    }
    rebuild_course_cache($courseid, true);
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
    $items = [];
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
    }
    if (!$items) {
        return '';
    }

    $config = json_encode([
        'wwwroot'  => $CFG->wwwroot,
        'courseid' => $courseid,
        'userid'   => (int)$USER->id,
        'items'    => $items,
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
})();
</script>
JS;

    return $js;
}
