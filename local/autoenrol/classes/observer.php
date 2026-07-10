<?php
namespace local_autoenrol;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->dirroot . '/lib/enrollib.php');

/**
 * Event observer: auto-enrols any logged-in, non-guest user into a course
 * the first time they view it — provided the course has a manual enrol instance.
 */
class observer {

    /**
     * Triggered by \core\event\course_viewed.
     *
     * @param \core\event\course_viewed $event
     */
    public static function course_viewed(\core\event\course_viewed $event): void {
        global $DB;

        $userid   = $event->userid;
        $courseid = $event->courseid;

        // Skip guests, admin-as views, and the site front page.
        if (!$userid || $courseid <= 1) {
            return;
        }

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
        if (!$user || isguestuser($user)) {
            return;
        }

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return;
        }

        // Already enrolled? Nothing to do.
        if (is_enrolled($context, $userid, '', true)) {
            return;
        }

        // Find the enabled manual enrol instance for this course.
        $instance = $DB->get_record('enrol', [
            'courseid' => $courseid,
            'enrol'    => 'manual',
            'status'   => 0,   // ENROL_INSTANCE_ENABLED
        ]);

        if (!$instance) {
            // No manual enrol instance — skip silently.
            return;
        }

        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            return;
        }

        // Use the instance's default role (usually Student = 5).
        $roleid = $instance->roleid ?: 5;

        $plugin->enrol_user($instance, $userid, $roleid);
    }
}
