<?php
/**
 * Event observers for local_autoenrol.
 *
 * Listens for course_created so that every new course automatically gets
 * an enrol_autoenrol instance added to it.
 * (Actual auto-enrollment is handled by enrol/autoenrol via try_autoenrol().)
 */
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_created',
        'callback'  => '\local_autoenrol\observer::course_created',
    ],
];
