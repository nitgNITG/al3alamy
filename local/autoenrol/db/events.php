<?php
/**
 * Event observers for local_autoenrol.
 * Fires on every course_viewed event and auto-enrolls the user if not already enrolled.
 */
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_viewed',
        'callback'  => '\local_autoenrol\observer::course_viewed',
    ],
];
