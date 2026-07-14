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
 * Inject a "الاشتراكات" link into the navbar for logged-in non-admin users, and
 * (on the site front page) render the subscription-plans grid after the last
 * content section.
 *
 * The nav link is placed next to the other menu titles (the primary navigation),
 * not next to the user avatar. A gold badge shows the remaining days when the
 * user has an active subscription.
 */
function local_subscriptions_before_standard_top_of_body_html() {
    global $USER, $CFG, $PAGE;

    // Only for logged-in non-guests, non-admins.
    if (!isloggedin() || isguestuser() || is_siteadmin()) {
        return '';
    }

    // Primary destination is the plans catalogue (the cards). From there the
    // active-subscription banner links to "my subscriptions".
    $plansurl  = $CFG->wwwroot . '/local/subscriptions/index.php';

    // Compute days remaining if there is an active subscription.
    $days_badge = '';
    $active_sub = null;
    try {
        $active_sub = \local_subscriptions\manager::get_active_subscription($USER->id);
        if ($active_sub) {
            $days_left = max(0, (int)ceil(($active_sub->expiry_time - time()) / 86400));
            $days_badge = '<span style="background:#c8a84b;color:#fff;font-size:11px;'
                . 'padding:2px 7px;border-radius:10px;margin-inline-start:5px;font-weight:bold;'
                . 'vertical-align:middle;">' . $days_left . ' يوم</span>';
        }
    } catch (\Throwable $e) {
        // Silently ignore if tables not yet installed.
    }

    $label = 'الاشتراكات';

    $js = <<<JS
<script>
(function() {
    var URL = '{$plansurl}';
    var LABEL = '{$label}';
    var BADGE = '{$days_badge}';

    // A nav-item styled to match the theme's primary navigation titles.
    function makeNavItem() {
        var li = document.createElement('li');
        li.id = 'local-subscriptions-nav-link';
        li.className = 'nav-item';
        li.setAttribute('role', 'none');
        var a = document.createElement('a');
        a.className = 'nav-link';
        a.setAttribute('role', 'menuitem');
        a.href = URL;
        a.style.cssText = 'display:inline-flex;align-items:center;gap:5px;';
        a.innerHTML = '<span>' + LABEL + '</span>' + BADGE;
        li.appendChild(a);
        return li;
    }

    // Fallback link (used only if the primary nav list is not found).
    function makeLink(padding, color) {
        var a = document.createElement('a');
        a.href = URL;
        a.style.cssText = 'text-decoration:none;color:' + color + ';padding:' + padding
            + ';display:inline-flex;align-items:center;gap:4px;';
        a.innerHTML = BADGE + '<span>' + LABEL + '</span>';
        return a;
    }

    function insertSubscriptionsLink() {
        if (document.getElementById('local-subscriptions-nav-link')) return true;

        // Preferred: the primary navigation title list, next to the other menu items.
        var menu = document.querySelector('.primary-navigation ul.more-nav') ||
                   document.querySelector('nav.moremenu ul.more-nav') ||
                   document.querySelector('ul.ace-responsive-menu');
        if (menu) {
            var item = makeNavItem();
            // Sit before the "More" overflow dropdown when present, else at the end.
            var more = menu.querySelector('.dropdownmoremenu');
            if (more) { menu.insertBefore(item, more); } else { menu.appendChild(item); }
            return true;
        }

        // Fallback: the user area, so the link is never lost.
        var container = document.querySelector('ul.sign_up_btn') ||
                        document.querySelector('.usermenu') ||
                        document.querySelector('.navbar-nav') ||
                        document.querySelector('header ul') ||
                        document.querySelector('header nav');
        if (!container) return false;

        var li = document.createElement('li');
        li.id = 'local-subscriptions-nav-link';
        li.style.cssText = 'list-style:none;display:inline-flex;align-items:center;';
        li.appendChild(makeLink('6px 12px', '#fff'));
        container.insertBefore(li, container.firstChild);
        return true;
    }

    // Guaranteed fallback: a floating pill so the plans page is always reachable
    // even if the theme header has no recognised container.
    function insertFloating() {
        if (document.getElementById('local-subscriptions-fab')) return;
        var box = document.createElement('div');
        box.id = 'local-subscriptions-fab';
        box.style.cssText = 'position:fixed;inset-inline-end:16px;bottom:16px;z-index:99999;'
            + 'background:#2d6a9f;border-radius:24px;box-shadow:0 3px 12px rgba(0,0,0,.25);'
            + 'padding:8px 16px;';
        box.appendChild(makeLink('0', '#fff'));
        document.body.appendChild(box);
    }

    var floatingTries = 0;
    function ensure() {
        // Already placed among the nav titles? Nothing to do.
        if (document.getElementById('local-subscriptions-nav-link')) return;
        if (!insertSubscriptionsLink()) {
            // No known container yet — keep the floating pill as a last resort.
            if (floatingTries++ > 6) { insertFloating(); }
        }
    }

    function run() {
        ensure();
        // The theme's responsive-menu JS can rebuild the nav after load and drop
        // our item; re-assert it a few times so it survives that re-render.
        [300, 800, 1500, 2500].forEach(function (d) { setTimeout(ensure, d); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
</script>
JS;

    // On the site front page, also render the plans grid after the last section.
    if ($PAGE->pagetype === 'site-index') {
        $js .= local_subscriptions_home_plans_script($active_sub);
    }

    return $js;
}

/**
 * Build the front-page subscription-plans section (same cards as index.php) and a
 * small script that injects it after the front page's content blocks — i.e. right
 * after the last section ("طلابنا الأعزاء") and before the footer.
 *
 * @param \stdClass|null $active_sub The user's active subscription, if any.
 * @return string HTML/JS to append, or '' when there are no active plans.
 */
function local_subscriptions_home_plans_script($active_sub) {
    global $DB;

    try {
        $plans = \local_subscriptions\manager::get_plans(true); // Active only.
    } catch (\Throwable $e) {
        return '';
    }
    if (empty($plans)) {
        return '';
    }

    ob_start();
    ?>
    <div class="ls-home-plans">
      <style>
        .ls-home-plans { direction: rtl; font-family: 'Segoe UI', Tahoma, Arial, sans-serif; padding: 50px 15px 60px; background: #f7f9fc; }
        .ls-home-inner { max-width: 1100px; margin: 0 auto; }
        .ls-home-title { color: #2d6a9f; text-align: center; font-size: 1.9em; font-weight: 800; margin: 0 0 6px; }
        .ls-home-sub { color: #666; text-align: center; margin: 0 0 32px; font-size: 1em; }
        .ls-home-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px; }
        .ls-home-card { position: relative; background: #fff; border: 1px solid #dee2e6; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; flex-direction: column; transition: box-shadow 0.2s; }
        .ls-home-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.12); }
        .ls-home-card.current-plan { border: 2px solid #c8a84b; box-shadow: 0 4px 16px rgba(200,168,75,0.25); }
        .ls-home-card .current-plan-badge {
            position: absolute; top: -12px; inset-inline-start: 20px;
            background: #c8a84b; color: #fff; font-size: .78em; font-weight: 700;
            padding: 4px 12px; border-radius: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        .ls-home-btn.current-plan-btn { background: #c8a84b; cursor: default; }
        .ls-home-cardlink { text-decoration: none; color: inherit; display: block; }
        .ls-home-name { font-size: 1.25em; font-weight: 700; color: #1a1a1a; margin-bottom: 8px; }
        .ls-home-price { font-size: 2em; font-weight: 800; color: #c8a84b; margin-bottom: 12px; }
        .ls-home-price span { font-size: .45em; font-weight: 400; color: #888; vertical-align: middle; margin-right: 4px; }
        .ls-home-desc { color: #555; font-size: .93em; line-height: 1.6; margin-bottom: 14px; }
        .ls-home-meta { font-size: .85em; color: #666; margin-bottom: 14px; }
        .ls-home-meta span { display: inline-block; background: #f0f4fa; border-radius: 6px; padding: 3px 10px; margin-left: 6px; margin-bottom: 4px; }
        .ls-home-btn { display: block; text-align: center; background: #2d6a9f; color: #fff; padding: 11px; border-radius: 8px; font-size: 1em; font-weight: 600; text-decoration: none; transition: background 0.2s; margin-top: auto; }
        .ls-home-btn:hover { background: #1d5080; color: #fff; }
        .ls-home-btn.subscribed { background: #28a745; cursor: default; }
      </style>
      <div class="ls-home-inner">
        <h2 class="ls-home-title"><?php echo get_string('plans_list', 'local_subscriptions'); ?></h2>
        <p class="ls-home-sub">اختر الخطة المناسبة لك واستمتع بالوصول إلى المحتوى التعليمي</p>
        <div class="ls-home-grid">
          <?php foreach ($plans as $plan): ?>
            <?php
              $item_count = $DB->count_records('local_subscriptions_items', ['planid' => $plan->id]);
              $is_current_plan = $active_sub && (int)$active_sub->planid === (int)$plan->id;
            ?>
            <div class="ls-home-card<?php echo $is_current_plan ? ' current-plan' : ''; ?>">
              <?php if ($is_current_plan): ?>
                <div class="current-plan-badge">★ <?php echo get_string('current_plan_badge', 'local_subscriptions'); ?></div>
              <?php endif; ?>
              <a class="ls-home-cardlink" href="<?php echo (new moodle_url('/local/subscriptions/plan.php', ['id' => $plan->id]))->out(); ?>">
                <div class="ls-home-name"><?php echo s($plan->name); ?></div>
                <div class="ls-home-price"><?php echo number_format((float)$plan->price, 0); ?><span>جنيه مصري</span></div>
                <?php if ($plan->description): ?>
                  <div class="ls-home-desc"><?php echo nl2br(s($plan->description)); ?></div>
                <?php endif; ?>
                <div class="ls-home-meta">
                  <?php if ($plan->course_access_type === \local_subscriptions\manager::COURSE_ACCESS_ALL): ?>
                    <span>جميع المقررات</span>
                  <?php else: ?>
                    <span><?php echo $item_count; ?> مقرر</span>
                  <?php endif; ?>
                  <?php if ($plan->expiry_type === \local_subscriptions\manager::EXPIRY_DAYS): ?>
                    <span><?php echo (int)$plan->expiry_days; ?> يوم</span>
                  <?php else: ?>
                    <span>حتى <?php echo $plan->expiry_date ? userdate($plan->expiry_date, '%d/%m/%Y') : '-'; ?></span>
                  <?php endif; ?>
                </div>
              </a>
              <?php if ($is_current_plan): ?>
                <div class="ls-home-btn current-plan-btn">✓ <?php echo get_string('current_plan', 'local_subscriptions'); ?></div>
              <?php elseif ($active_sub): ?>
                <div class="ls-home-btn subscribed">✓ <?php echo get_string('already_subscribed', 'local_subscriptions'); ?></div>
              <?php else: ?>
                <a class="ls-home-btn" href="<?php echo (new moodle_url('/local/subscriptions/buy.php', ['planid' => $plan->id]))->out(); ?>"><?php echo get_string('subscribe_now', 'local_subscriptions'); ?></a>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php
    $html = ob_get_clean();
    $payload = json_encode($html, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $js = <<<JS
<script>
(function () {
  var HTML = {$payload};
  function place() {
    if (document.getElementById('ls-home-plans-wrap')) return;
    var wrap = document.createElement('div');
    wrap.id = 'ls-home-plans-wrap';
    wrap.innerHTML = HTML;
    // Insert right after the front page's content block region (the last section
    // is "طلابنا الأعزاء"), above the footer.
    var region = document.getElementById('block-region-fullwidth-top');
    if (region && region.parentNode) {
      region.parentNode.insertBefore(wrap, region.nextSibling);
      return;
    }
    var footer = document.querySelector('section.footer_bottom_area') ||
                 document.querySelector('#page-footer') ||
                 document.querySelector('footer');
    if (footer && footer.parentNode) {
      footer.parentNode.insertBefore(wrap, footer);
    } else {
      document.body.appendChild(wrap);
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', place);
  } else {
    place();
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
