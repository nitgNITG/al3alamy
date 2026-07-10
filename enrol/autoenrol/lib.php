<?php
// This file is part of Moodle - http://moodle.org/
defined('MOODLE_INTERNAL') || die();

/**
 * Auto-enrolment plugin for al3alamy.com
 *
 * Automatically enrols any authenticated non-guest user the first time they
 * visit a course enrolment page.  Moodle calls try_autoenrol() (and checks
 * can_self_enrol() first) from enrol/index.php BEFORE any page output, so
 * the redirect to the course page is clean and header-safe.
 */
class enrol_autoenrol_plugin extends enrol_plugin {

    // ------------------------------------------------------------------ //
    // Required interface                                                   //
    // ------------------------------------------------------------------ //

    /**
     * Return true only for logged-in, non-guest users who are not already
     * enrolled.  This tells Moodle to call try_autoenrol().
     */
    public function can_self_enrol(stdClass $instance, $checkuserenrolment = true) {
        global $USER;

        if (!isloggedin() || isguestuser()) {
            return get_string('noguestaccess', 'enrol');
        }

        if ($checkuserenrolment) {
            $context = context_course::instance($instance->courseid);
            if (is_enrolled($context, $USER->id)) {
                return get_string('alreadyenrolled', 'enrol');
            }
        }

        return true;
    }

    /**
     * Silently enrol the user.
     * Return '' on success (empty string = Moodle will redirect to course).
     */
    public function try_autoenrol(stdClass $instance) {
        global $USER;

        try {
            $roleid = !empty($instance->roleid) ? (int)$instance->roleid : 5; // 5 = student
            $this->enrol_user($instance, $USER->id, $roleid);
            return '';   // empty string signals success to enrol/index.php
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    // ------------------------------------------------------------------ //
    // Administration                                                       //
    // ------------------------------------------------------------------ //

    public function allow_enrol(stdClass $instance)   { return false; }
    public function allow_unenrol(stdClass $instance) { return false; }
    public function allow_manage(stdClass $instance)  { return false; }

    /**
     * Default field values used when adding a new instance to a course.
     */
    protected function get_instance_defaults(): array {
        return [
            'roleid'     => 5,   // student
            'customint1' => 1,
        ];
    }

    /**
     * Add an autoenrol instance to a course with sensible defaults.
     * Called from db/install.php and from external code.
     */
    public function add_default_instance(stdClass $course): int {
        return $this->add_instance($course, $this->get_instance_defaults());
    }
}
