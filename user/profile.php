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
 * Public Profile -- a user's public profile page
 *
 * - each user can currently have their own page (cloned from system and then customised)
 * - users can add any blocks they want
 * - the administrators can define a default site public profile for users who have
 *   not created their own public profile
 *
 * This script implements the user's view of the public profile, and allows editing
 * of the public profile.
 *
 * @package    core_user
 * @copyright  2010 Remote-Learner.net
 * @author     Hubert Chathi <hubert@remote-learner.net>
 * @author     Olav Jordan <olav.jordan@remote-learner.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/my/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/filelib.php');

$userid         = optional_param('id', 0, PARAM_INT);
$edit           = optional_param('edit', null, PARAM_BOOL);    // Turn editing on and off.
$reset          = optional_param('reset', null, PARAM_BOOL);

$PAGE->set_url('/user/profile.php', array('id' => $userid));

if (!empty($CFG->forceloginforprofiles)) {
    require_login();
    if (isguestuser()) {
        $PAGE->set_context(context_system::instance());
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('guestcantaccessprofiles', 'error'),
            get_login_url(),
            $CFG->wwwroot
        );
        echo $OUTPUT->footer();
        die;
    }
} else if (!empty($CFG->forcelogin)) {
    require_login();
}

$userid = $userid ? $userid : $USER->id;       // Owner of the page.
if ((!$user = $DB->get_record('user', array('id' => $userid))) || ($user->deleted)) {
    $PAGE->set_context(context_system::instance());
    echo $OUTPUT->header();
    if (!$user) {
        echo $OUTPUT->notification(get_string('invaliduser', 'error'));
    } else {
        echo $OUTPUT->notification(get_string('userdeleted'));
    }
    echo $OUTPUT->footer();
    die;
}

$currentuser = ($user->id == $USER->id);
$context = $usercontext = context_user::instance($userid, MUST_EXIST);

if (!user_can_view_profile($user, null, $context)) {

    // Course managers can be browsed at site level. If not forceloginforprofiles, allow access (bug #4366).
    $struser = get_string('user');
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title("$SITE->shortname: $struser");  // Do not leak the name.
    $PAGE->set_heading($struser);
    $PAGE->set_pagelayout('mypublic');
    $PAGE->add_body_class('limitedwidth');
    $PAGE->set_url('/user/profile.php', array('id' => $userid));
    $PAGE->navbar->add($struser);
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('usernotavailable', 'error'));
    echo $OUTPUT->footer();
    exit;
}

// Get the profile page.  Should always return something unless the database is broken.
if (!$currentpage = my_get_page($userid, MY_PAGE_PUBLIC)) {
    throw new \moodle_exception('mymoodlesetup');
}

$PAGE->set_context($context);
$PAGE->set_pagelayout('mypublic');
$PAGE->add_body_class('limitedwidth');
$PAGE->set_pagetype('user-profile');

// Set up block editing capabilities.
if (isguestuser()) {     // Guests can never edit their profile.
    $USER->editing = $edit = 0;  // Just in case.
    $PAGE->set_blocks_editing_capability('moodle/my:configsyspages');  // unlikely :).
} else {
    if ($currentuser) {
        $PAGE->set_blocks_editing_capability('moodle/user:manageownblocks');
    } else {
        $PAGE->set_blocks_editing_capability('moodle/user:manageblocks');
    }
}

// Start setting up the page.
$strpublicprofile = get_string('publicprofile');

$PAGE->blocks->add_region('content');
$PAGE->set_subpage($currentpage->id);
$PAGE->set_title(fullname($user) . ": $strpublicprofile");
$PAGE->set_heading(fullname($user));

if (!$currentuser) {
    $PAGE->navigation->extend_for_user($user);
    if ($node = $PAGE->settingsnav->get('userviewingsettings' . $user->id)) {
        $node->forceopen = true;
    }
} else if ($node = $PAGE->settingsnav->get('dashboard', navigation_node::TYPE_CONTAINER)) {
    $node->forceopen = true;
}
if ($node = $PAGE->settingsnav->get('root')) {
    $node->forceopen = false;
}


// Toggle the editing state and switches.
if ($PAGE->user_allowed_editing()) {
    if ($reset !== null) {
        if (!is_null($userid)) {
            if (!$currentpage = my_reset_page($userid, MY_PAGE_PUBLIC, 'user-profile')) {
                throw new \moodle_exception('reseterror', 'my');
            }
            redirect(new moodle_url('/user/profile.php', array('id' => $userid)));
        }
    } else if ($edit !== null) {             // Editing state was specified.
        $USER->editing = $edit;       // Change editing state.
    } else {                          // Editing state is in session.
        if ($currentpage->userid) {   // It's a page we can edit, so load from session.
            if (!empty($USER->editing)) {
                $edit = 1;
            } else {
                $edit = 0;
            }
        } else {
            // For the page to display properly with the user context header the page blocks need to
            // be copied over to the user context.
            if (!$currentpage = my_copy_page($userid, MY_PAGE_PUBLIC, 'user-profile')) {
                throw new \moodle_exception('mymoodlesetup');
            }
            $PAGE->set_context($usercontext);
            $PAGE->set_subpage($currentpage->id);
            // It's a system page and they are not allowed to edit system pages.
            $USER->editing = $edit = 0;          // Disable editing completely, just to be safe.
        }
    }

    // Add button for editing page.
    $params = array('edit' => !$edit, 'id' => $userid);

    $resetbutton = '';
    $resetstring = get_string('resetpage', 'my');
    $reseturl = new moodle_url("$CFG->wwwroot/user/profile.php", array('edit' => 1, 'reset' => 1, 'id' => $userid));

    if (!$currentpage->userid) {
        // Viewing a system page -- let the user customise it.
        $editstring = get_string('updatemymoodleon');
        $params['edit'] = 1;
    } else if (empty($edit)) {
        $editstring = get_string('updatemymoodleon');
        $resetbutton = $OUTPUT->single_button($reseturl, $resetstring);
    } else {
        $editstring = get_string('updatemymoodleoff');
        $resetbutton = $OUTPUT->single_button($reseturl, $resetstring);
    }

    $url = new moodle_url("$CFG->wwwroot/user/profile.php", $params);
    $button = '';
    if (!$PAGE->theme->haseditswitch) {
        $button = $OUTPUT->single_button($url, $editstring);
    }
    $PAGE->set_button($resetbutton . $button);
} else {
    $USER->editing = $edit = 0;
}

// Trigger a user profile viewed event.
profile_view($user, $usercontext);

// TODO WORK OUT WHERE THE NAV BAR IS!
echo $OUTPUT->header();

/* user data */
$ccnUserHandler = new ccnUserHandler();
$ccnUser = $ccnUserHandler->ccnGetUserDetails($userid);

//check if user is a teacher ANYWHERE in Moodle
$teacherRole = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'));
$isTeacher = $DB->record_exists('role_assignments', ['userid' => $userid, 'roleid' => $teacherRole]);

echo '
<link href="https://cdn.jsdelivr.net/npm/aos@2.3.1/dist/aos.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init();
</script>

';

if ($isTeacher) {

    $editurl = new moodle_url("$CFG->wwwroot/user/edit.php", array('id' => $userid));

    echo '<style>
        .ccn_breadcrumb_widgets {display: none !important;}
        .image_sec {
            display: flex;
            flex-direction: column;
        }
        .image_sec img {
            height: auto;
        }
        .col h1,
        .col ul li {
            color: #00126C;
        }
        .data_block {
            display: flex;
            flex-direction: column;
            gap: 25px;
            background-color: #00126C;
        }
        #data h3 {
            color: #fff;
        }
        #social {
            display: flex;
            gap: 15px;
        }
        #users_confirmed {
            margin-top: 30px !important;
            width: 100% !important;
            background-color: #00126C !important;
        }
        #users_confirmed:hover {
            color: #fff;
        }
        #about_text {
            display: flex;
            justify-content: center;
            text-align: center;
            align-items: center;
            color: #00126C;
        }
    </style>';

    $profile_img = $DB->get_field('teacher_profile_img', 'image_path', array('user_id' => $USER->id));

    echo '<div class="main_container" id="main_container" data-aos="fade-up" data-aos-duration="800">
      <div class="row about_block" data-aos="fade-right" data-aos-duration="800">
        <div class="col">
          <div class="image_sec">
          ';
    if (!$profile_img) {
        echo '
                <input type="file" id="newProfileImage" accept=".jpg, .jpeg, .png, .svg">
                <input type="hidden" id="currentProfileImage" value="' . $profile_img . '">
                ';
    } else {
        // delete button to delete image from database and directory

        // Display the image
        echo '<img id="profileImage" src="' . $profile_img . '" data-aos="zoom-in" data-aos-duration="800">';

        // Add the delete button
        echo '
                <a href="delete_profileIMG.php?user_id=' . $USER->id . '">Delete Image</a>
            ';
    }
    echo '
      </div>
    </div>
    <div class="col mt-3" id="about_text" data-aos="fade-left" data-aos-duration="800">
    ' . $user->description . '
    </div>
  </div>

  <script>
    document.addEventListener(\'DOMContentLoaded\', function() {
        const newProfileImageInput = document.getElementById(\'newProfileImage\');
        const currentProfileImageInput = document.getElementById(\'currentProfileImage\');
        const profileImage = document.getElementById(\'profileImage\');
    
        newProfileImageInput.addEventListener(\'change\', function() {
            const file = newProfileImageInput.files[0];
            const formData = new FormData();
            formData.append(\'new_profile_img\', file);
    
            // Send AJAX request
            const xhr = new XMLHttpRequest();
            xhr.open(\'POST\', \'update_profileIMG.php\');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    const newImagePath = xhr.responseText;
                    updateProfileImage(newImagePath);        
                } else {
                    alert(\'Error uploading image.\');
                }
            };
            xhr.send(formData);
        });

        // Function to update the profile image
        function updateProfileImage(newImagePath) {
            profileImage.src = newImagePath;
            currentProfileImageInput.value = newImagePath;
        }
    });
  </script>

  <div class="data_block p-5 mt-5" data-aos="fade-up" data-aos-duration="800">
    <div id="data">
      <h3><strong>Name: </strong>' . $ccnUser->firstname . '</h3>
      <h3><strong>Phone Number: </strong>' . $ccnUser->phone1 . '</h3>
      <h3><strong>WhatsApp Number: </strong>' . $ccnUser->phone2 . '</h3>
    </div>
    <div id="social">
      <div><a href="#"><img src="../service_images/fbicon.png" data-aos="fade-in" data-aos-duration="800"></a></div>
      <div><a href="#"><img src="../service_images/youticon.png" data-aos="fade-in" data-aos-duration="800"></a></div>
      <div><a href="https://wa.me/0201011111111"><img src="../service_images/whatsicon.png" data-aos="fade-in" data-aos-duration="800"></a></div>
    </div>
    <div class="container_top" style="display: flex; gap: 15px;justify-content: flex-end;font-size: 20px;" data-aos="fade-left" data-aos-duration="800">
      <a href="' . $editurl . '">
        <i class="fa fa-edit" style="color: #fff;"></i>
      </a>
      <a href="#">
        <i class="fa fa-bookmark" style="color: #fff;"></i>
      </a>
    </div>
  </div>
  </div>';

    //print_r($users);
} else {
    $editurl = new moodle_url("$CFG->wwwroot/user/edit.php", array('id' => $userid));
    echo '
    <style>
        .ccn_breadcrumb_widgets {display: none !important;}
        #region-main .row .col-xl-12 .row:nth-child(1) {display: none;}
        #page-user-profile .userprofile section.our-team {
            display: none;
        }
        .ccn_breadcrumb_style_4 #ccn_instructor_personal_infor {
            display: none;
        }
        .profile_user_img {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .userinitials {
            border-radius: 2% !important;
            margin-right: 0rem !important;
            width: 200px;
            height: 200px;
        }
      
        .grid-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-gap: 45px;
            padding: 15px;
        }
        @media only screen and (max-width: 600px) {
            .grid-container {
                display: flex;
                flex-direction: column;
                grid-gap: 35px;
            }
        }
       
        .item_box {
            padding: 10px;
            border-radius: 0px;
            border-bottom: 2px solid #00126C;
            background: #FFF;
        }
       
        .grid-item label {
            color: #154372;
            font-family: Bodoni Moda;
            font-size: 20px;
            font-style: normal;
            font-weight: 400;
            line-height: normal;
        }
        #profession16, #whatsapp5, #youtube4, #facebook3 {display: none;}
    
        .header_container h1 {
            color: #00126C;
            font-size: 30px;
        }
        .profile_cover {
            display: flex;
            justify-content: center;
            background: #00126C;
        }
        #ccn-main-region {
            padding-top: 30px !important;
        }
    </style>
    
    <div class="profile_cover mb-5" data-aos="fade-down" data-aos-duration="800">
        <div class="profile_user_img" style="padding: 80px;">
            ' . $ccnUser->printAvatar . '
            <br>
            <h1 class="user_fname" style="color: #fff;">' . $ccnUser->firstname . '</h1>
        </div>
    </div>
    
    <div style="display: flex; gap: 15px;justify-content: space-between;">
        <div class="header_container" data-aos="fade-right" data-aos-duration="800">
            <h1>Personal Information</h1>
        </div>
        <div class="container_top" style="display: flex; gap: 15px;padding: 20px 40px; font-size: 20px;" data-aos="fade-left" data-aos-duration="800">
            <a href="' . $editurl . '">
                <i class="fa fa-edit" style="color: #00126C;"></i>
            </a>
            <a href="#">
                <i class="fa fa-bookmark" style="color: #00126C;"></i>
            </a>
        </div>
    </div>
    
    <div class="grid-container" data-aos="fade-up" data-aos-duration="800">
    ';

    if ($ccnUser->firstname) {
        echo '
    <div class="grid-item">
    <label>Name</label><br>
    <div class="item_box">' . $ccnUser->firstname . '</div>
    </div>
    ';
    }
    /* if ($ccnUser->lastname) {
    echo '
    <div class="grid-item">
    <label>Lastname</label><br>
    <div class="item_box">' . $ccnUser->lastname . '</div>
    </div>
    ';
     } */
    if ($ccnUser->email) {
        echo '
    <div class="grid-item">
    <label>Email</label><br>
    <div class="item_box">' . $ccnUser->email . '</div>
    </div>
    ';
    }
    if ($ccnUser->phone1) {
        echo '
    <div class="grid-item">
    <label>Phone Number</label><br>
    <div class="item_box">' . $ccnUser->phone1 . '</div>
    </div>
    ';
    }
    if ($ccnUser->phone2) {
        echo '
    <div class="grid-item">
    <label>WhatsApp Number</label><br>
    <div class="item_box">' . $ccnUser->phone2 . '</div>
    </div>
    ';
    }
    if ($ccnUser->department) {
        echo '
    <div class="grid-item">
    <label>Department</label><br>
    <div class="item_box">' . $ccnUser->department . '</div>
    </div>
    ';
    }
    if ($ccnUser->institution) {
        echo '
    <div class="grid-item">
    <label>Institution</label><br>
    <div class="item_box">' . $ccnUser->institution . '</div>
    </div>
    ';
    }
    if ($user->address) {
        echo '
    <div class="grid-item">
    <label>Address</label><br>
    <div class="item_box">' . $user->address . '</div>
    </div>
    ';
    }
    if ($ccnUser->tab) {
        echo '
    <div class="grid-item">
    <label>Tab</label><br>
    <div class="item_box">' . $ccnUser->tab . '</div>
    </div>
    ';
    }
    if ($ccnUser->country) {
        echo '
    <div class="grid-item">
    <label>Country</label><br>
    <div class="item_box">' . $ccnUser->country . '</div>
    </div>
    ';
    }

    $school = $DB->get_field('user_info_data', 'data', array('userid' => $userid, 'fieldid' => 1));
    $center = $DB->get_field('user_info_data', 'data', array('userid' => $userid, 'fieldid' => 2));
    $parentnumper = $DB->get_field('user_info_data', 'data', array('userid' => $userid, 'fieldid' => 3));

    if ($school) {
        echo '
    <div class="grid-item">
    <label>School</label><br>
    <div class="item_box">' . $school . '</div>
    </div>
    ';
    }

    if ($center) {
        echo '
    <div class="grid-item">
    <label>Educational Center</label><br>
    <div class="item_box">' . $center . '</div>
    </div>
    ';
    }

    if ($parentnumper) {
        echo '
    <div class="grid-item">
    <label>Parent Phone Number</label><br>
    <div class="item_box">' . $parentnumper . '</div>
    </div>
    ';
    }
}


/* Get admins */
$admins = get_admins();
$isadmin = false;
foreach ($admins as $admin) {
    if ($userid == $admin->id) {
        $isadmin = true;
        break;
    }
}

if ($isadmin) {

    // Dashboard button
    echo '<a href="' . $CFG->wwwroot . '/new" type="button" class="btn btn-primary mt-5 mb-5">Dashboard</a>';

    echo '<style>
    #main_container { display: none; }
    </style>';

    echo '<div class="userprofile">';

    $hiddenfields = [];
    if (!has_capability('moodle/user:viewhiddendetails', $usercontext)) {
        $hiddenfields = array_flip(explode(',', $CFG->hiddenuserfields));
    }
    if ($user->description && !isset($hiddenfields['description'])) {
        echo '<div class="description">';
        if (
            !empty($CFG->profilesforenrolledusersonly) && !$currentuser &&
            !$DB->record_exists('role_assignments', array('userid' => $user->id))
        ) {
            echo get_string('profilenotshown', 'moodle');
        } else {
            $user->description = file_rewrite_pluginfile_urls(
                $user->description,
                'pluginfile.php',
                $usercontext->id,
                'user',
                'profile',
                null
            );
            echo format_text($user->description, $user->descriptionformat);
        }
        echo '</div>';
    }

    echo $OUTPUT->heading(get_string('userprofile', 'core_user'), 2, 'sr-only');
    echo $OUTPUT->custom_block_region('content');

    // Render custom blocks.
    $renderer = $PAGE->get_renderer('core_user', 'myprofile');
    $tree = core_user\output\myprofile\manager::build_tree($user, $currentuser);
    echo $renderer->render($tree);

    echo '</div>';  // Userprofile class.
}
echo $OUTPUT->footer();
