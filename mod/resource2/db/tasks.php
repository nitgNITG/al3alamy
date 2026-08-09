<?php
/**
 * Scheduled task definitions for mod_resource2.
 *
 * @package    mod_resource2
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname'   => 'mod_resource2\task\cleanup_orphan_uploads',
        'blocking'    => 0,
        // Run once a day at 03:00 (server time) — low-traffic window.
        'minute'      => '0',
        'hour'        => '3',
        'day'         => '*',
        'month'       => '*',
        'dayofweek'   => '*',
        'disabled'    => 0,
    ],
];
