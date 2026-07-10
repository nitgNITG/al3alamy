<?php
// This file is part of Moodle - http://moodle.org/
defined('MOODLE_INTERNAL') || die();

/**
 * Runs once when the plugin is installed.
 * Adds an autoenrol instance to every existing course so that current
 * students are enrolled on their next visit.
 */
function xmldb_enrol_autoenrol_install() {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/enrol/autoenrol/lib.php');

    /** @var enrol_autoenrol_plugin $plugin */
    $plugin  = enrol_get_plugin('autoenrol');
    $courses = $DB->get_records('course', [], 'id', 'id');

    foreach ($courses as $row) {
        if ($row->id == SITEID) {
            continue;   // never touch the site-level pseudo-course
        }
        // Avoid duplicates if the admin re-installs.
        if ($DB->record_exists('enrol', ['courseid' => $row->id, 'enrol' => 'autoenrol'])) {
            continue;
        }
        $course = $DB->get_record('course', ['id' => $row->id]);
        $plugin->add_default_instance($course);
    }
}
