<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Library functions for local_deviceregistration.
 *
 * @package    local_deviceregistration
 */

defined('MOODLE_INTERNAL') || die();

/** Name of the persistent per-browser device-token cookie. */
define('LOCAL_DEVICEREGISTRATION_COOKIE', 'MDL_DEVREG_DID');

/**
 * Whether device registration control is enabled site-wide.
 *
 * @return bool
 */
function local_deviceregistration_is_enabled(): bool {
    return (bool) get_config('local_deviceregistration', 'enabled');
}

/**
 * Maximum number of registered devices allowed per user.
 *
 * Returns 0 when the feature is disabled, meaning unlimited devices.
 *
 * @return int the configured limit, or 0 for unlimited (feature off).
 */
function local_deviceregistration_max_devices(): int {
    if (!local_deviceregistration_is_enabled()) {
        return 0; // Feature disabled: unlimited devices.
    }

    $max = (int) get_config('local_deviceregistration', 'maxdevices');
    return $max > 0 ? $max : 1;
}

/**
 * Current browser's device token, read from the cookie.
 *
 * @return string sanitised token, or '' when none is present.
 */
function local_deviceregistration_get_cookie_token(): string {
    $raw = $_COOKIE[LOCAL_DEVICEREGISTRATION_COOKIE] ?? '';
    return preg_replace('/[^a-zA-Z0-9]/', '', (string) $raw);
}

/**
 * Persist a device token in a long-lived cookie on the current browser.
 *
 * @param string $token
 * @return void
 */
function local_deviceregistration_set_cookie_token(string $token): void {
    global $CFG;

    $options = [
        'expires'  => time() + (2 * YEARSECS),
        'path'     => !empty($CFG->sessioncookiepath) ? $CFG->sessioncookiepath : '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (!empty($CFG->sessioncookiedomain)) {
        $options['domain'] = $CFG->sessioncookiedomain;
    }

    setcookie(LOCAL_DEVICEREGISTRATION_COOKIE, $token, $options);

    // Make it available within the same request too.
    $_COOKIE[LOCAL_DEVICEREGISTRATION_COOKIE] = $token;
}

/**
 * Current request's user agent, trimmed to the stored column length.
 *
 * @return string
 */
function local_deviceregistration_useragent(): string {
    return \core_text::substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
}

/**
 * Generate a cryptographically random 64-char device token.
 *
 * @return string
 */
function local_deviceregistration_generate_token(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Core enforcement: called immediately after complete_user_login().
 *
 * Logic:
 *  1. Read device token from cookie.  If none, generate a fresh one.
 *  2. If this token is already registered for the user → update last-seen,
 *     refresh cookie, return true  (known device, always allowed).
 *  3. If token is unknown → count how many devices this user already has.
 *       – Under limit : register the new device, set cookie, return true.
 *       – At/over limit: return false  (caller must block the login).
 *
 * Site admins are unconditionally allowed (caller should check before calling).
 *
 * @param  int    $userid
 * @param  string $token  Value from local_deviceregistration_get_cookie_token()
 *                        (may be empty when the browser has no cookie yet).
 * @return bool   true = allow login,  false = block login (device limit reached)
 */
function local_deviceregistration_check_and_register(int $userid, string $token): bool {
    global $DB;

    // Generate a fresh token when the browser has no cookie.
    if ($token === '') {
        $token = local_deviceregistration_generate_token();
    }

    // ── Known device? ─────────────────────────────────────────────────────
    $existing = $DB->get_record('local_devreg_device', [
        'userid'      => $userid,
        'devicetoken' => $token,
    ]);

    if ($existing) {
        // Refresh last-seen metadata, re-set cookie (extends expiry), allow.
        $DB->update_record('local_devreg_device', (object) [
            'id'           => $existing->id,
            'lastip'       => getremoteaddr(),
            'timelastseen' => time(),
        ]);
        local_deviceregistration_set_cookie_token($token);
        return true;
    }

    // ── New device — check limit ──────────────────────────────────────────
    $max   = local_deviceregistration_max_devices();
    $count = (int) $DB->count_records('local_devreg_device', ['userid' => $userid]);

    if ($max > 0 && $count >= $max) {
        return false; // Limit reached — block.
    }

    // ── Register new device ───────────────────────────────────────────────
    $DB->insert_record('local_devreg_device', (object) [
        'userid'       => $userid,
        'devicetoken'  => $token,
        'useragent'    => local_deviceregistration_useragent(),
        'lastip'       => getremoteaddr(),
        'timecreated'  => time(),
        'timelastseen' => time(),
    ]);
    local_deviceregistration_set_cookie_token($token);
    return true;
}

/**
 * Terminate the just-created session and bounce the user back to the login
 * page with a "device limit reached" message (hard block).
 *
 * This does not return.
 *
 * @return void
 */
function local_deviceregistration_block_login(): void {
    // End the session that login just established.
    require_logout();

    redirect(
        new moodle_url('/login/index.php'),
        get_string('devicelimitreached', 'local_deviceregistration'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

/**
 * Add a "My devices" link to the user's Preferences page.
 *
 * @param navigation_node $navigation
 * @param stdClass $user
 * @param context_user $usercontext
 * @param stdClass $course
 * @param context_course $coursecontext
 * @return void
 */
function local_deviceregistration_extend_navigation_user_settings($navigation, $user, $usercontext, $course, $coursecontext) {
    global $USER;

    // Only the account owner manages their own devices, and only while the feature is on.
    if (empty($USER->id) || $USER->id != $user->id || !local_deviceregistration_is_enabled()) {
        return;
    }

    $node = navigation_node::create(
        get_string('mydevices', 'local_deviceregistration'),
        new moodle_url('/local/deviceregistration/mydevices.php'),
        navigation_node::TYPE_SETTING,
        null,
        'local_deviceregistration_mydevices'
    );
    $navigation->add_node($node);
}
