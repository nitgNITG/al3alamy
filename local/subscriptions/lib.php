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
