<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * English strings for local_deviceregistration.
 *
 * @package    local_deviceregistration
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Device Registration';

// Settings — enable/disable.
$string['enabled']      = 'Enable device registration control';
$string['enabled_desc'] = 'When enabled, each user may only sign in from a limited number of registered devices. When disabled, users can register an unlimited number of devices.';

// Settings — maximum devices.
$string['maxdevices']      = 'Maximum registered devices per user';
$string['maxdevices_desc'] = 'The maximum number of devices each user is allowed to register. Must be greater than zero. This limit is enforced whenever a user attempts to register a new device. Changing the limit does not remove devices that are already registered.';

// Validation.
$string['error_maxdevices'] = 'The maximum number of devices must be greater than zero.';

// Management page.
$string['page_intro']          = 'Control how many devices each user can use to sign in to the platform.';
$string['settings_heading']    = 'Device registration settings';
$string['settings_saved']      = 'Device registration settings saved.';
$string['savechanges']         = 'Save changes';
$string['label_feature_status'] = 'Feature status';
$string['label_current_limit'] = 'Devices per user';
$string['status_enabled']      = 'Enabled';
$string['status_disabled']     = 'Disabled';
$string['unlimited']           = 'Unlimited';

// Enforcement.
$string['devicelimitreached'] = 'You are already logged in on another device. Please log out from your other device first before signing in here.';

// My devices page.
$string['mydevices']          = 'My devices';
$string['mydevices_intro']    = 'These are the devices you have used to sign in. Remove a device to free a slot if you have reached your limit.';
$string['devices_registered'] = 'Devices registered';
$string['devices_allowed']    = 'Devices allowed';
$string['nodevices']          = 'You have no registered devices yet.';
$string['device']             = 'Device';
$string['lastip']             = 'Last IP';
$string['firstseen']          = 'First registered';
$string['lastseen']           = 'Last used';
$string['actions']            = 'Actions';
$string['remove']             = 'Remove';
$string['confirm_remove']     = 'Remove this device? It will need to be registered again next time you sign in from it.';
$string['device_removed']     = 'Device removed.';
$string['thisdevice']         = 'This device';
$string['unknowndevice']      = 'Unknown device';

// Admin: force logout tool.
$string['forcelogout_title']               = 'Force logout users';
$string['forcelogout_intro']               = 'Find a user to see their current sessions and log them out of another device if a stale session is blocking their next login.';
$string['forcelogout_search_placeholder']  = 'Username, email, or name';
$string['forcelogout_search_btn']          = 'Search';
$string['forcelogout_nomatch']             = 'No matching users.';
$string['forcelogout_nosessions']          = 'This user has no active sessions.';
$string['forcelogout_col_started']         = 'Started';
$string['forcelogout_col_lastactive']      = 'Last active';
$string['forcelogout_col_ip']              = 'IP address';
$string['forcelogout_action']              = 'Log out';
$string['forcelogout_action_all']          = 'Log out of all sessions';
$string['forcelogout_confirm_one']         = 'Log out this session?';
$string['forcelogout_confirm_all']         = 'Log this user out of all their devices?';
$string['forcelogout_session_done']        = 'Session logged out successfully.';
$string['forcelogout_all_done']            = 'User logged out of all sessions successfully.';

// Privacy.
$string['privacy:metadata'] = 'The Device Registration plugin only stores site configuration settings and does not store any personal data.';
