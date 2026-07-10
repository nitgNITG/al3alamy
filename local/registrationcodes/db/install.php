<?php
/**
 * Post-install hook: create the student profile fields.
 * On a fresh install upgrade.php is NOT called, so we need this file.
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_registrationcodes_install() {
    global $DB;
    // Reuse the shared helper defined in upgrade.php.
    require_once(__DIR__ . '/upgrade.php');
    local_registrationcodes_ensure_profile_fields($DB);
}
