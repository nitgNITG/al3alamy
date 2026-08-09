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
 * Adds or updates modules in a course using new formslib
 *
 * @package    moodlecore
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../config.php");
require_once("lib.php");
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/plagiarismlib.php');
require_once($CFG->dirroot . '/course/modlib.php');

$add    = optional_param('add', '', PARAM_ALPHANUM);     // Module name.
$update = optional_param('update', 0, PARAM_INT);
$return = optional_param('return', 0, PARAM_BOOL);    //return to course/view.php if false or mod/modname/view.php if true
$type   = optional_param('type', '', PARAM_ALPHANUM); //TODO: hopefully will be removed in 2.0
$sectionreturn = optional_param('sr', null, PARAM_INT);

$url = new moodle_url('/course/modedit.php');
$url->param('sr', $sectionreturn);
if (!empty($return)) {
    $url->param('return', $return);
}

if (!empty($add)) {
    $section = required_param('section', PARAM_INT);
    $course  = required_param('course', PARAM_INT);

    $url->param('add', $add);
    $url->param('section', $section);
    $url->param('course', $course);
    $PAGE->set_url($url);

    $course = $DB->get_record('course', array('id' => $course), '*', MUST_EXIST);
    require_login($course);

    // There is no page for this in the navigation. The closest we'll have is the course section.
    // If the course section isn't displayed on the navigation this will fall back to the course which
    // will be the closest match we have.
    navigation_node::override_active_url(course_get_url($course, $section));

    // MDL-69431 Validate that $section (url param) does not exceed the maximum for this course / format.
    // If too high (e.g. section *id* not number) non-sequential sections inserted in course_sections table.
    // Then on import, backup fills 'gap' with empty sections (see restore_rebuild_course_cache). Avoid this.
    $courseformat = course_get_format($course);
    $maxsections = $courseformat->get_max_sections();
    if ($section > $maxsections) {
        print_error('maxsectionslimit', 'moodle', '', $maxsections);
    }

    list($module, $context, $cw, $cm, $data) = prepare_new_moduleinfo_data($course, $add, $section);
    $data->return = 0;
    $data->sr = $sectionreturn;
    $data->add = $add;
    if (!empty($type)) { //TODO: hopefully will be removed in 2.0
        $data->type = $type;
    }

    $sectionname = get_section_name($course, $cw);
    $fullmodulename = get_string('modulename', $module->name);

    if ($data->section && $course->format != 'site') {
        $heading = new stdClass();
        $heading->what = $fullmodulename;
        $heading->to   = $sectionname;
        $pageheading = get_string('addinganewto', 'moodle', $heading);
    } else {
        $pageheading = get_string('addinganew', 'moodle', $fullmodulename);
    }
    $navbaraddition = $pageheading;
} else if (!empty($update)) {

    $url->param('update', $update);
    $PAGE->set_url($url);

    // Select the "Edit settings" from navigation.
    navigation_node::override_active_url(new moodle_url('/course/modedit.php', array('update' => $update, 'return' => 1)));

    // Check the course module exists.
    $cm = get_coursemodule_from_id('', $update, 0, false, MUST_EXIST);

    // Check the course exists.
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

    // require_login
    require_login($course, false, $cm); // needed to setup proper $COURSE

    list($cm, $context, $module, $data, $cw) = get_moduleinfo_data($cm, $course);
    $data->return = $return;
    $data->sr = $sectionreturn;
    $data->update = $update;

    $sectionname = get_section_name($course, $cw);
    $fullmodulename = get_string('modulename', $module->name);

    if ($data->section && $course->format != 'site') {
        $heading = new stdClass();
        $heading->what = $fullmodulename;
        $heading->in   = $sectionname;
        $pageheading = get_string('updatingain', 'moodle', $heading);
    } else {
        $pageheading = get_string('updatinga', 'moodle', $fullmodulename);
    }
    $navbaraddition = null;
} else {
    require_login();
    print_error('invalidaction');
}

$pagepath = 'mod-' . $module->name . '-';
if (!empty($type)) { //TODO: hopefully will be removed in 2.0
    $pagepath .= $type;
} else {
    $pagepath .= 'mod';
}
$PAGE->set_pagetype($pagepath);
$PAGE->set_pagelayout('admin');

$modmoodleform = "$CFG->dirroot/mod/$module->name/mod_form.php";
if (file_exists($modmoodleform)) {
    require_once($modmoodleform);
} else {
    print_error('noformdesc');
}

$mformclassname = 'mod_' . $module->name . '_mod_form';
$mform = new $mformclassname($data, $cw->section, $cm, $course);
$mform->set_data($data);
$check = 0;
if ($module->name == "resource") {
    if ($update) {
        $check = 1;
    }
} elseif ($module->name == "resource2") {
    if ($update) {
        $check = 1;
    }
}

if ($mform->is_cancelled()) {
    if ($return && !empty($cm->id)) {
        $urlparams = [
            'id' => $cm->id, // We always need the activity id.
            'forceview' => 1, // Stop file downloads in resources.
        ];
        $activityurl = new moodle_url("/mod/$module->name/view.php", $urlparams);
        redirect($activityurl);
    } else {
        redirect(course_get_url($course, $cw->section, array('sr' => $sectionreturn)));
    }
} else if ($fromform = $mform->get_data()) {
    if (!empty($fromform->update)) {
        list($cm, $fromform) = update_moduleinfo($cm, $fromform, $course, $mform);
    } else if (!empty($fromform->add)) {
        $fromform = add_moduleinfo($fromform, $course, $mform);
    } else {
        print_error('invaliddata');
    }

    if (isset($fromform->submitbutton)) {
        $url = new moodle_url("/mod/$module->name/view.php", array('id' => $fromform->coursemodule, 'forceview' => 1));
        if (empty($fromform->showgradingmanagement)) {
            $record = $DB->get_record("course_modules", array('instance' => $fromform->id));

            if ($module->name == "resource") {
                $url = $CFG->wwwroot . "/test/submit.php?resource_id=" . $fromform->id . "&update=" . $check;
            } elseif ($module->name == "resource2") {
                $url = $CFG->wwwroot . "/test2/submit1.php?resource_id=" . $fromform->id . "&update=" . $check;
            }

            redirect($url);
        } else {
            redirect($fromform->gradingman->get_management_url($url));
        }
    } else {
        redirect(course_get_url($course, $cw->section, array('sr' => $sectionreturn)));
    }
    exit;

    /* if (isset($fromform->submitbutton)) {
        $url = new moodle_url("/mod/$module->name/view.php", array('id' => $fromform->coursemodule, 'forceview' => 1));
        if (empty($fromform->showgradingmanagement)) {
            $record = $DB->get_record("course_modules", array('instance' => $fromform->id));

            $url = $CFG->wwwroot . "/test2/submit1.php?resource_id=" . $fromform->id . "&update=" . $check . "";
            if (!empty($record) && ($module->name == "resource")) {
                $url = $CFG->wwwroot . "/test/submit.php?resource_id=" . $fromform->id . "&update=" . $check . "";
            } elseif (!empty($record) && ($module->name == "resource2")) {
                $url = $CFG->wwwroot . "/test2/submit1.php?resource_id=" . $fromform->id . "&update=" . $check . "";
            } elseif (!empty($record) && ($module->name == "resource3")) {
                $url = $CFG->wwwroot . "/test3/index.php?resource_id=" . $fromform->id . "&update=" . $check . "";
            }
            redirect($url);
        } else {
            redirect($fromform->gradingman->get_management_url($url));
        }
    } else {
        redirect(course_get_url($course, $cw->section, array('sr' => $sectionreturn)));
    }
    exit; */
} else {

    $streditinga = get_string('editinga', 'moodle', $fullmodulename);
    $strmodulenameplural = get_string('modulenameplural', $module->name);

    if (!empty($cm->id)) {
        $context = context_module::instance($cm->id);
    } else {
        $context = context_course::instance($course->id);
    }

    $PAGE->set_heading($course->fullname);
    $PAGE->set_title($streditinga);
    $PAGE->set_cacheable(false);

    if (isset($navbaraddition)) {
        $PAGE->navbar->add($navbaraddition);
    }

    echo $OUTPUT->header();

    if ($add == 'resource2') {
        require_once($CFG->dirroot . '/course/externallib.php');
        require_once($CFG->dirroot . '/user/externallib.php');
        require_once($CFG->libdir . "/weblib.php");
        require_once($CFG->dirroot . '/webservice/lib.php');
        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->dirroot . '/theme/edumy/ccn/block_handler/ccn_block_handler.php');
        require_once($CFG->dirroot . '/course/renderer.php');
        require_once($CFG->dirroot . '/theme/edumy/ccn/course_handler/ccn_course_handler.php');
        require_once($CFG->dirroot . '/theme/edumy/ccn/user_handler/ccn_user_handler.php');

        global $DB, $CFG, $USER;
        $act_sql = "SELECT cm.*
                FROM {course_modules} cm
                JOIN {modules} m ON cm.module = m.id
                WHERE m.name = :module_name AND deletioninprogress != 1";

        $selectedActivity = $add;
        $act_params = array('module_name' => $selectedActivity);
        $activities = $DB->get_records_sql($act_sql, $act_params);
        $count_activities = count($activities);



        // Get maximum allowed video count
        $maximum = $DB->get_field('count_activities', 'count', array('id' => 1));
        $admins = get_admins();

        foreach ($admins as $admin) {
            if ($USER->id == $admin->id) {
                $num = 30;
                echo '
                <form method="POST" action="./update_maximum.php">
                    <label for="maximumNumber">Maximum Number:</label>
                    <input style="text-align: center;font-size: 20px;" type="number" id="maximumNumber" value="' . $maximum . '" name="maximumNumber">
                    <input type="submit" value="Update">
                </form>
                ';
            }
        }
    }

    if ($add == 'resource2') {
        if ($count_activities >= $maximum) {
            echo '
            <style>
              .errorbox {
                  border: 1px solid #f44336;
                  background-color: #ffebee;
                  color: #f44336;
                  padding: 20px;
                  border-radius: 5px;
              }
              
              .errorcode {
                  font-size: 24px;
                  font-weight: bold;
              }
              
              .errormessage {
                  font-size: 16px;
                  margin-top: 10px;
              }        
            </style>
            <div data-rel="fatalerror" class="box py-3 errorbox alert alert-danger">
                <p class="errorcode" style="font-size: 20px; font-weight: bold;">
                    <i class="fa fa-exclamation-triangle"></i> Error: You have uploaded ' . $count_activities . ' videos.
                </p>
                <p class="errormessage">
                    You have reached the maximum allowed video count. Please delete some videos to upload a new one.
                </p>
            </div>
            ';
        } else {
            $available = $maximum - $count_activities;
            echo '
            <style>
              @keyframes ring {
                  0%, 100% {
                      transform: translateX(0);
                  }
                  50% {
                      transform: translateX(10px); /* Move right */
                  }
              }
      
              .bell {
                  padding: 15px;
                  background-color: #f4f42936;
                  display: flex;
                  gap: 28px;
              }
              .fa-bell {
                  font-size: 30px;
                  color: #f1c40f; /* Bell icon color */
                  rotate: 337deg;
                  animation: ring 1s ease-in-out infinite;
              }
            </style>
            <div class="bell">
            <i class="fa fa-bell" aria-hidden="true"></i> Number of videos available: ' . $available . '
            </div>';

            $teacherRoleID = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'));
            $teacherRole = $DB->record_exists('role_assignments', ['userid' => $USER->id, 'roleid' => $teacherRoleID]);

            if (get_string_manager()->string_exists('modulename_help', $module->name)) {
                echo $OUTPUT->heading_with_help($pageheading, 'modulename', $module->name, 'icon');
            } else {
                echo $OUTPUT->heading_with_help($pageheading, '', $module->name, 'icon');
            }
            $teachers2 = $DB->get_records_sql("SELECT CONCAT(u.firstname, ' ', u.lastname)  AS name, u.id as id
            FROM   mo_course c 
            LEFT OUTER JOIN   mo_context cx ON c.id = cx.instanceid 
            LEFT OUTER JOIN   mo_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3' 
            LEFT OUTER JOIN   mo_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND c.id=" . $COURSE->id . "");
            $teach = 0;
            $admins = get_admins();
            $isadmin = false;
            foreach ($admins as $admin) {
                if ($USER->id == $admin->id) {
                    $isadmin = true;
                    break;
                }
            }
            $roleassignments = $DB->get_records('role_assignments', ['userid' => $USER->id]);
            $manager = 0;
            foreach ($roleassignments as $role) {
                if ($role->roleid == 1) {
                    $manager = 1;
                    break;
                }
            }
            if ($isadmin || $manager == 1 || $teacherRole) {

                $mform->display();
            } else {
                foreach ($teachers2 as $teacher) {
                    $teach = $teacher->id;
                }
                if (!empty($teach)) {
                    $check_availability = $DB->get_record('control_activities', array('course' => $COURSE->id, 'teacher_id' => $teach));
                    if (($add == "quiz" && $check_availability->quiz == "1") || ($add == "assign" && $check_availability->assign == "1") || ($add == "resource" && $check_availability->file == "1") || ($add == "page" && $check_availability->page == "1") || !empty($update) || ($add == "testnew" && $check_availability->pdf == "1") || ($add == "url" && $check_availability->url == "1") || ($add == "resource2" && $check_availability->empty1 == "1")) {
                        $mform->display();
                    } else {
                        echo "
                <div class='alert alert-danger' role='alert'>
                " . get_string("permission", "theme_edumy") . "
                </div>
                ";
                            }
                        }
                    }
                    if ($module->name == "resource2") {
                        echo '<style>
                #id_submitbutton2{
                    display:none;
                }
                #fitem_id_files {display: none;}
                </style>
                ';
                        echo '  <script>
            
                document.querySelector("#id_submitbutton").value="Next";
            
                </script>';
                    }
        }
    } else {

        $teacherRoleID = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'));
        $teacherRole = $DB->record_exists('role_assignments', ['userid' => $USER->id, 'roleid' => $teacherRoleID]);

        if (get_string_manager()->string_exists('modulename_help', $module->name)) {
            echo $OUTPUT->heading_with_help($pageheading, 'modulename', $module->name, 'icon');
        } else {
            echo $OUTPUT->heading_with_help($pageheading, '', $module->name, 'icon');
        }
        $teachers2 = $DB->get_records_sql("SELECT CONCAT(u.firstname, ' ', u.lastname)  AS name, u.id as id
        FROM   mo_course c 
        LEFT OUTER JOIN   mo_context cx ON c.id = cx.instanceid 
        LEFT OUTER JOIN   mo_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3' 
        LEFT OUTER JOIN   mo_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND c.id=" . $COURSE->id . "");
        $teach = 0;
        $admins = get_admins();
        $isadmin = false;
        foreach ($admins as $admin) {
            if ($USER->id == $admin->id) {
                $isadmin = true;
                break;
            }
        }
        $roleassignments = $DB->get_records('role_assignments', ['userid' => $USER->id]);
        $manager = 0;
        foreach ($roleassignments as $role) {
            if ($role->roleid == 1) {
                $manager = 1;
                break;
            }
        }
        if ($isadmin || $manager == 1 || $teacherRole) {

            $mform->display();
        } else {
            foreach ($teachers2 as $teacher) {
                $teach = $teacher->id;
            }
            if (!empty($teach)) {
                $check_availability = $DB->get_record('control_activities', array('course' => $COURSE->id, 'teacher_id' => $teach));
                if (($add == "quiz" && $check_availability->quiz == "1") || ($add == "assign" && $check_availability->assign == "1") || ($add == "resource" && $check_availability->file == "1") || ($add == "page" && $check_availability->page == "1") || !empty($update) || ($add == "testnew" && $check_availability->pdf == "1") || ($add == "url" && $check_availability->url == "1") || ($add == "resource2" && $check_availability->empty1 == "1")) {
                    $mform->display();
                } else {
                    echo "
        <div class='alert alert-danger' role='alert'>
        " . get_string("permission", "theme_edumy") . "
        </div>
        ";
                }
            }
        }
        if ($module->name == "resource2") {
            echo '<style>
        #id_submitbutton2{
            display:none;
        }
        #fitem_id_files {display: none;}
        </style>
        ';
            echo '  <script>
    
        document.querySelector("#id_submitbutton").value="Next";
    
        </script>';
        }
    }
    echo $OUTPUT->footer();
}
