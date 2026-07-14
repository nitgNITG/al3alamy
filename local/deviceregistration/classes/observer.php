<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_deviceregistration;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers.
 *
 * @package    local_deviceregistration
 */
class observer {

    /**
     * Enforce the per-user concurrent session limit right after a successful login.
     *
     * Checks how many OTHER active sessions this user already has.
     * If the count is >= the configured maximum, the new login is refused
     * and the just-created session is terminated. The user must log out
     * from an existing device before they can log in from a new one.
     *
     * @param \core\event\user_loggedin $event
     */
    public static function user_loggedin(\core\event\user_loggedin $event) {
        global $DB, $CFG;

        require_once(__DIR__ . '/../lib.php');

        if (!local_deviceregistration_is_enabled()) {
            return; // Feature off — unlimited devices.
        }

        $userid = (int) $event->objectid;

        // Never lock out site administrators.
        if (is_siteadmin($userid)) {
            return;
        }

        $max = local_deviceregistration_max_devices();
        if ($max <= 0) {
            return; // 0 = unlimited.
        }

        // Get the current session ID (the one just created by this login).
        $currentsid = session_id();

        // Count OTHER active sessions for this user (excluding the current one).
        try {
            $sql = "SELECT COUNT(*)
                      FROM {sessions}
                     WHERE userid = :userid
                       AND sid <> :sid
                       AND timemodified > :cutoff";

            // Consider sessions active if they were touched within the last 24 hours,
            // or use Moodle's session timeout if configured.
            $timeout = !empty($CFG->sessiontimeout) ? $CFG->sessiontimeout : 86400;
            $cutoff = time() - $timeout;

            $params = [
                'userid' => $userid,
                'sid'    => $currentsid,
                'cutoff' => $cutoff,
            ];

            $othersessions = $DB->count_records_sql($sql, $params);
            
            // --- DEBUG LOGGING ---
            $log = date('Y-m-d H:i:s') . " - user_loggedin - userid: $userid, sid: $currentsid, othersessions: $othersessions, max: $max, sql: $sql, params: " . json_encode($params) . "\n";
            file_put_contents('d:\\My work\\NIT\\Projects\\al3alamy\\debug_log.txt', $log, FILE_APPEND);
            // ---------------------
        } catch (\dml_exception $e) {
            // --- DEBUG LOGGING ---
            $log = date('Y-m-d H:i:s') . " - dml_exception: " . $e->getMessage() . "\n";
            file_put_contents('d:\\My work\\NIT\\Projects\\al3alamy\\debug_log.txt', $log, FILE_APPEND);
            // ---------------------
            debugging('local_deviceregistration: skipping enforcement - ' . $e->getMessage(), DEBUG_DEVELOPER);
            return; // Fail open — never break login.
        }

        // If user already has max sessions active on other devices → block this login.
        if ($othersessions >= $max) {
            local_deviceregistration_block_login(); // Redirects and exits.
            return; // Guard.
        }
    }
}
