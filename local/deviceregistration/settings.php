<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin navigation entry for local_deviceregistration.
 *
 * Implements US-AD-11-1: Configure Device Registration Settings.
 * Registers standard Moodle settings under Site administration > Plugins > Local plugins.
 *
 * @package    local_deviceregistration
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Create the settings page
    $settings = new admin_settingpage('local_deviceregistration', get_string('pluginname', 'local_deviceregistration'));

    // Enable / disable toggle
    $settings->add(new admin_setting_configcheckbox(
        'local_deviceregistration/enabled',
        get_string('enabled', 'local_deviceregistration'),
        get_string('enabled_desc', 'local_deviceregistration'),
        0
    ));

    // Maximum devices
    $settings->add(new admin_setting_configtext(
        'local_deviceregistration/maxdevices',
        get_string('maxdevices', 'local_deviceregistration'),
        get_string('maxdevices_desc', 'local_deviceregistration'),
        1,
        PARAM_INT
    ));

    // Moodle's admin/settings/plugins.php will automatically add this page to 'localplugins' category.
}
