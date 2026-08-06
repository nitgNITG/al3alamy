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
     * Enforce the per-user device limit right after a successful login.
     *
     * Uses a persistent browser cookie (device token) to identify devices.
     * Site admins and managers are unconditionally allowed.
     *
     * @param \core\event\user_loggedin $event
     */
    public static function user_loggedin(\core\event\user_loggedin $event) {
        global $DB;

        require_once(__DIR__ . '/../lib.php');

        if (!local_deviceregistration_is_enabled()) {
            return; // Feature off — unlimited devices.
        }

        $userid = (int) $event->objectid;

        // Never lock out site administrators.
        if (is_siteadmin($userid)) {
            return;
        }

        // Never lock out managers (system-level manager role).
        $systemcontext = \context_system::instance();
        $managerroles  = get_archetype_roles('manager');
        foreach ($managerroles as $role) {
            if ($DB->record_exists('role_assignments', [
                'userid'    => $userid,
                'roleid'    => $role->id,
                'contextid' => $systemcontext->id,
            ])) {
                return; // User is a manager — skip device check.
            }
        }

        $max = local_deviceregistration_max_devices();
        if ($max <= 0) {
            return; // 0 = unlimited.
        }

        // ── Device-token check (cookie-based) ────────────────────────────────
        $token   = local_deviceregistration_get_cookie_token();
        $allowed = local_deviceregistration_check_and_register($userid, $token);

        // --- DEBUG LOGGING ---
        $log = date('Y-m-d H:i:s') . " - user_loggedin - userid: $userid, token: " . ($token ?: '(new)') . ", allowed: " . ($allowed ? 'yes' : 'NO') . ", max: $max\n";
        file_put_contents(__DIR__ . '/debug_log.txt', $log, FILE_APPEND);
        // ---------------------

        if (!$allowed) {
            local_deviceregistration_block_login(); // Redirects and exits.
        }
    }
}
