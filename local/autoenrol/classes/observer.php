<?php
namespace local_autoenrol;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for local_autoenrol.
 *
 * Whenever a new course is created, this adds an enrol_autoenrol instance to it
 * so that the next visitor is automatically enrolled.
 *
 * Bulk-adding to existing courses is handled by enrol/autoenrol/db/install.php.
 */
class observer {

    /**
     * Triggered by \core\event\course_created.
     * Adds an autoenrol enrolment instance to the new course.
     *
     * @param \core\event\course_created $event
     */
    public static function course_created(\core\event\course_created $event): void {
        global $DB, $CFG;

        $courseid = $event->objectid;
        if (!$courseid || $courseid == SITEID) {
            return;
        }

        // Ensure the enrol_autoenrol plugin is available.
        $plugin_file = $CFG->dirroot . '/enrol/autoenrol/lib.php';
        if (!file_exists($plugin_file)) {
            return;
        }
        require_once($plugin_file);

        // Skip if an autoenrol instance already exists (e.g. template copy).
        if ($DB->record_exists('enrol', ['courseid' => $courseid, 'enrol' => 'autoenrol'])) {
            return;
        }

        /** @var \enrol_autoenrol_plugin $plugin */
        $plugin  = enrol_get_plugin('autoenrol');
        $course  = $DB->get_record('course', ['id' => $courseid]);
        if ($course) {
            $plugin->add_default_instance($course);
        }
    }
}
