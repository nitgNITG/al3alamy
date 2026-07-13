<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

/**
 * Inject a "اشتراكاتي" link into the navbar for logged-in non-admin users.
 * Also shows a gold badge with remaining days if the user has an active subscription.
 */
function local_subscriptions_before_standard_top_of_body_html() {
    global $USER, $CFG;

    // Only for logged-in non-guests, non-admins.
    if (!isloggedin() || isguestuser() || is_siteadmin()) {
        return '';
    }

    $mysubsurl = $CFG->wwwroot . '/local/subscriptions/mysubscriptions.php';

    // Compute days remaining if there is an active subscription.
    $days_badge = '';
    try {
        $sub = \local_subscriptions\manager::get_active_subscription($USER->id);
        if ($sub) {
            $days_left = max(0, (int)ceil(($sub->expiry_time - time()) / 86400));
            $days_badge = '<span style="
                background: #c8a84b;
                color: #fff;
                font-size: 11px;
                padding: 2px 7px;
                border-radius: 10px;
                margin-right: 5px;
                font-weight: bold;
                vertical-align: middle;
            ">' . $days_left . ' يوم</span>';
        }
    } catch (\Throwable $e) {
        // Silently ignore if tables not yet installed.
    }

    $label = 'اشتراكاتي';

    $js = <<<JS
<script>
(function() {
    function insertSubscriptionsLink() {
        var container = document.querySelector('ul.sign_up_btn');
        if (!container) {
            // Try alternative selectors used by some Moodle themes.
            container = document.querySelector('.usermenu') ||
                        document.querySelector('.navbar-nav') ||
                        document.querySelector('nav .nav');
        }
        if (!container) return;

        // Avoid duplicate insertion.
        if (document.getElementById('local-subscriptions-nav-link')) return;

        var li = document.createElement('li');
        li.id = 'local-subscriptions-nav-link';
        li.style.cssText = 'list-style:none;display:inline-flex;align-items:center;';

        var a = document.createElement('a');
        a.href = '{$mysubsurl}';
        a.style.cssText = 'color:#fff;text-decoration:none;padding:6px 12px;display:flex;align-items:center;gap:4px;';
        a.innerHTML = '{$days_badge}<span>{$label}</span>';

        li.appendChild(a);
        container.insertBefore(li, container.firstChild);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', insertSubscriptionsLink);
    } else {
        insertSubscriptionsLink();
    }
})();
</script>
JS;

    return $js;
}

/**
 * On course pages, add an "Unlock with subscription" button to each locked paid
 * lesson that belongs to the current user's active credit-plan and that they have
 * not unlocked yet. Sits alongside videopay's "Buy" button (buy OR unlock).
 *
 * @return string HTML/JS appended near the footer.
 */
function local_subscriptions_before_footer() {
    global $PAGE, $USER, $CFG, $COURSE;

    // Course view pages only, real courses only, never while editing.
    if (strpos((string)$PAGE->pagetype, 'course-view') !== 0) {
        return '';
    }
    if (!isloggedin() || isguestuser() || is_siteadmin()) {
        return '';
    }
    $courseid = (int)$COURSE->id;
    if ($courseid <= 1 || $PAGE->user_is_editing()) {
        return '';
    }

    try {
        $sub = \local_subscriptions\manager::get_active_subscription($USER->id);
        if (!$sub) {
            return '';
        }
        if (\local_subscriptions\manager::get_unlock_limit_for($sub) <= 0) {
            return ''; // Not a credit plan.
        }

        $pool = \local_subscriptions\manager::get_pool_cmids((int)$sub->planid);
        if (!$pool) {
            return '';
        }

        // Keep only the pool lessons that are on THIS course and still locked for the user.
        $modinfo = get_fast_modinfo($courseid);
        $locked = [];
        foreach ($pool as $cmid => $groupid) {
            try {
                $cminfo = $modinfo->get_cm((int)$cmid);
            } catch (\Throwable $e) {
                continue; // Not in this course / deleted.
            }
            if (!$cminfo->available) { // Availability not met = still locked.
                $locked[] = (int)$cmid;
            }
        }
        if (!$locked) {
            return '';
        }

        $remaining = \local_subscriptions\manager::get_remaining_unlocks($sub);
    } catch (\Throwable $e) {
        return ''; // Tables not ready, etc.
    }

    $config = json_encode([
        'wwwroot'   => $CFG->wwwroot,
        'sesskey'   => sesskey(),
        'cmids'     => array_values($locked),
        'remaining' => (int)$remaining,
        'l_unlock'  => get_string('unlock_with_subscription', 'local_subscriptions'),
        'l_left'    => get_string('unlocks_remaining', 'local_subscriptions'),
        'l_none'    => get_string('no_credits_left', 'local_subscriptions'),
        'l_working' => get_string('unlocking', 'local_subscriptions'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $js = <<<JS
<script>
(function () {
  var CFG = {$config};

  function makeBtn(cmid) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ls-unlock-btn';
    btn.style.cssText = 'margin-inline-start:8px;padding:3px 14px;border:0;border-radius:8px;'
      + 'font-weight:700;cursor:pointer;font-size:13px;vertical-align:middle;color:#fff;'
      + (CFG.remaining > 0 ? 'background:#1a9c5b;' : 'background:#9aa0a6;cursor:not-allowed;');
    btn.textContent = CFG.remaining > 0
      ? (CFG.l_unlock + ' (' + CFG.l_left + ' ' + CFG.remaining + ')')
      : CFG.l_none;
    if (CFG.remaining <= 0) { btn.disabled = true; return btn; }

    btn.addEventListener('click', function (e) {
      e.preventDefault(); e.stopPropagation();
      if (btn.disabled) return;
      btn.disabled = true;
      var prev = btn.textContent;
      btn.textContent = CFG.l_working;

      var xhr = new XMLHttpRequest();
      xhr.open('POST', CFG.wwwroot + '/local/subscriptions/unlock.php', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;
        var res = null;
        try { res = JSON.parse(xhr.responseText); } catch (ex) {}
        if (res && res.status === 'success') {
          location.reload();
        } else {
          btn.disabled = false;
          btn.textContent = (res && res.message) ? res.message : prev;
          setTimeout(function () { btn.textContent = prev; }, 2500);
        }
      };
      xhr.send('cmid=' + encodeURIComponent(cmid) + '&sesskey=' + encodeURIComponent(CFG.sesskey));
    });
    return btn;
  }

  CFG.cmids.forEach(function (cmid) {
    var el = document.getElementById('module-' + cmid);
    if (!el) return;
    var title = el.querySelector('.activityname') || el.querySelector('.instancename')
      || el.querySelector('.activityinstance') || el;
    if (title.querySelector('.ls-unlock-btn')) return; // Avoid duplicates.
    // Show the lesson even if videopay hid its restriction text.
    var info = el.querySelector('.availabilityinfo');
    if (info) info.style.display = 'none';
    title.appendChild(makeBtn(cmid));
  });
})();
</script>
JS;

    return $js;
}
