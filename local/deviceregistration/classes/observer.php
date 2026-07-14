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
     * Recognises the current browser via a persistent device-token cookie.
     * A known device is allowed (and refreshed). A new device is registered
     * while the user is under their limit; once at the limit, the new device
     * is refused and the just-created session is terminated (hard block).
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

        $max = local_deviceregistration_max_devices();
        if ($max <= 0) {
            return; // 0 = unlimited.
        }

        $now   = time();
        $token = local_deviceregistration_get_cookie_token();

        // Read current device state. Fail open on any DB problem (e.g. the
        // table not yet created) so this plugin can never break site login.
        try {
            // Known device for this user → allow and refresh its last-seen info.
            if ($token) {
                $existing = $DB->get_record('local_devreg_device', [
                    'userid'      => $userid,
                    'devicetoken' => $token,
                ]);
                if ($existing) {
                    $existing->timelastseen = $now;
                    $existing->lastip       = getremoteaddr();
                    $existing->useragent    = local_deviceregistration_useragent();
                    $DB->update_record('local_devreg_device', $existing);
                    return;
                }
            }
            $count = $DB->count_records('local_devreg_device', ['userid' => $userid]);
        } catch (\dml_exception $e) {
            debugging('local_deviceregistration: skipping enforcement - ' . $e->getMessage(), DEBUG_DEVELOPER);
            return;
        }

        // New device, and the user is at their limit → refuse and end session.
        if ($count >= $max) {
            local_deviceregistration_block_login(); // Redirects and exits.
            return; // Guard, in case block_login() ever returns.
        }

        // Under the limit → register this device.
        if (!$token) {
            $token = random_string(40);
            local_deviceregistration_set_cookie_token($token);
        }

        $record = (object) [
            'userid'       => $userid,
            'devicetoken'  => $token,
            'useragent'    => local_deviceregistration_useragent(),
            'lastip'       => getremoteaddr(),
            'timecreated'  => $now,
            'timelastseen' => $now,
        ];
        try {
            $DB->insert_record('local_devreg_device', $record);
        } catch (\dml_exception $e) {
            debugging('local_deviceregistration: could not register device - ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
