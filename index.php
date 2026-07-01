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

/**
 * Moodle frontpage.
 *
 * @package    core
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!file_exists('./config.php')) {
    header('Location: install.php');
    die;
}

require_once('config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/filelib.php');

global $USER;

redirect_if_major_upgrade_required();

$have_wallet = $DB->get_record('user_wallet', array('user_id' => $USER->id));
if (!$have_wallet && !isguestuser() && isloggedin()) {  // check if is loggedin but not guestuser and not have wallet
?>
    <style>
        /* Popup container */
        .popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 999999;
        }

        /* Popup content */
        .popup .card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 300px;
            text-align: center;
            position: relative;
            align-items: center;
        }

        .popup img {
            max-width: 100px;
            margin-bottom: 10px;
        }

        .popup .cookieHeading {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
            margin-bottom: 10px;
        }

        .popup .cookieDescription {
            font-size: 14px;
            margin: 0;
            margin-bottom: 20px;
        }

        .popup .buttonContainer {
            display: flex;
            justify-content: center;
        }

        .popup .buttonContainer button {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .popup .acceptButton {
            background: #F4CE14;
            color: #45474B;
        }

        /* Close button */
        .popup .close {
            position: absolute;
            top: 20px;
            right: 20px;
            opacity: 1;
            background: none;
            border: none;
            font-size: 20px;
            color: #fff;
            cursor: pointer;
        }
    </style>
    <!-- Popup container -->
    <div class="popup" id="popup">
        <button type="button" class="close" onclick="closePopup();">
            <span aria-hidden="true">&times;</span>
        </button>
        <div class="card">
            <img src="./service_images/wallet.png" alt="صورة محفظة">
            <p class="cookieHeading">إنشاء محفظة إلكترونية</p>
            <p class="cookieDescription">للحصول على أفضل تجربة استخدام على موقعنا، نوصي بإنشاء محفظة إلكترونية.</p>
            <div class="buttonContainer">
                <button class="acceptButton" onclick="window.location.href='/e-wallet';">اِبْدَأْ الآن</button>
            </div>
        </div>
    </div>
    <script>
        // JavaScript function to close the popup
        function closePopup() {
            document.getElementById('popup').style.display = 'none';
        }

        // JavaScript to handle the popup display for demo purposes
        document.addEventListener('DOMContentLoaded', () => {
            const popup = document.getElementById('popup');

            // Function to show the popup
            function showPopup() {
                popup.style.display = 'flex';
            }

            // Show the popup initially (for demo purposes)
            showPopup();
        });
    </script>
<?php
}

$urlparams = array();
if (
    !empty($CFG->defaulthomepage) &&
    ($CFG->defaulthomepage == HOMEPAGE_MY || $CFG->defaulthomepage == HOMEPAGE_MYCOURSES) &&
    optional_param('redirect', 1, PARAM_BOOL) === 0
) {
    $urlparams['redirect'] = 0;
}
$PAGE->set_url('/', $urlparams);
$PAGE->set_pagelayout('frontpage');
$PAGE->add_body_class('limitedwidth');
$PAGE->set_other_editing_capability('moodle/course:update');
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_other_editing_capability('moodle/course:activityvisibility');

// Prevent caching of this page to stop confusion when changing page after making AJAX changes.
$PAGE->set_cacheable(false);

require_course_login($SITE);

$hasmaintenanceaccess = has_capability('moodle/site:maintenanceaccess', context_system::instance());

// If the site is currently under maintenance, then print a message.
if (!empty($CFG->maintenance_enabled) and !$hasmaintenanceaccess) {
    print_maintenance_message();
}

$hassiteconfig = has_capability('moodle/site:config', context_system::instance());

if ($hassiteconfig && moodle_needs_upgrading()) {
    redirect($CFG->wwwroot . '/' . $CFG->admin . '/index.php');
}

// If site registration needs updating, redirect.
\core\hub\registration::registration_reminder('/index.php');

if (get_home_page() != HOMEPAGE_SITE) {
    // Redirect logged-in users to My Moodle overview if required.
    $redirect = optional_param('redirect', 1, PARAM_BOOL);
    if (optional_param('setdefaulthome', false, PARAM_BOOL)) {
        set_user_preference('user_home_page_preference', HOMEPAGE_SITE);
    } else if (!empty($CFG->defaulthomepage) && ($CFG->defaulthomepage == HOMEPAGE_MY) && $redirect === 1) {
        // At this point, dashboard is enabled so we don't need to check for it (otherwise, get_home_page() won't return it).
        redirect($CFG->wwwroot . '/my/');
    } else if (!empty($CFG->defaulthomepage) && ($CFG->defaulthomepage == HOMEPAGE_MYCOURSES) && $redirect === 1) {
        redirect($CFG->wwwroot . '/my/courses.php');
    } else if (!empty($CFG->defaulthomepage) && ($CFG->defaulthomepage == HOMEPAGE_USER)) {
        $frontpagenode = $PAGE->settingsnav->find('frontpage', null);
        if ($frontpagenode) {
            $frontpagenode->add(
                get_string('makethismyhome'),
                new moodle_url('/', array('setdefaulthome' => true)),
                navigation_node::TYPE_SETTING
            );
        } else {
            $frontpagenode = $PAGE->settingsnav->add(get_string('frontpagesettings'), null, navigation_node::TYPE_SETTING, null);
            $frontpagenode->force_open();
            $frontpagenode->add(
                get_string('makethismyhome'),
                new moodle_url('/', array('setdefaulthome' => true)),
                navigation_node::TYPE_SETTING
            );
        }
    }
}

// Trigger event.
course_view(context_course::instance(SITEID));

$PAGE->set_pagetype('site-index');
$PAGE->set_docs_path('');
$editing = $PAGE->user_is_editing();
$PAGE->set_title($SITE->fullname);
$PAGE->set_heading($SITE->fullname);
$PAGE->set_secondary_active_tab('coursehome');

$courserenderer = $PAGE->get_renderer('core', 'course');

if ($hassiteconfig) {
    $editurl = new moodle_url('/course/view.php', ['id' => SITEID, 'sesskey' => sesskey()]);
    $editbutton = $OUTPUT->edit_button($editurl);
    $PAGE->set_button($editbutton);
}

echo $OUTPUT->header();
?>
<style>
    #inst15 .ccnBlockContent .container {
        background-color: #167B44;
    }

    #inst15 .ccnBlockContent .container .ccn-row-reverse .col-lg-6 {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #inst15 .ccnBlockContent .container .ccn-row-reverse {
        padding: 30px 0px;
    }

    .contact_block_ar h3,
    .contact_block_ar ul li {
        color: white;
        font-size: large;
    }
    #ccn-main-region {
        display: none;
    }
</style>
<?php
$ex_date = $DB->get_field('user', 'ex_date', array('id' => $USER->id));
$current_date = date('Y-m-d');

$siteformatoptions = course_get_format($SITE)->get_format_options();
$modinfo = get_fast_modinfo($SITE);
$modnamesused = $modinfo->get_used_module_names();

// Print Section or custom info.
if (!empty($CFG->customfrontpageinclude)) {
    // Pre-fill some variables that custom front page might use.
    $modnames = get_module_types_names();
    $modnamesplural = get_module_types_names(true);
    $mods = $modinfo->get_cms();

    include($CFG->customfrontpageinclude);
} else if ($siteformatoptions['numsections'] > 0) {
    echo $courserenderer->frontpage_section1();
}
// Include course AJAX.
include_course_ajax($SITE, $modnamesused);

echo $courserenderer->frontpage();

if ($editing && has_capability('moodle/course:create', context_system::instance())) {
    echo $courserenderer->add_new_course_button();
}

if (!empty($ex_date) && $ex_date < $current_date) {

    // Display an toast message using JavaScript
    echo "
    <script>
    alert('يرجي تجديد الاشتراك الشهري');
    </script>";
    // Log the user out
    redirect($CFG->wwwroot . "/login/logout.php?sesskey=" . sesskey());

    // Terminate the script
    exit;
}

echo $OUTPUT->footer();
