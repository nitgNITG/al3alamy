<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

// Allow site admins AND anyone with the manage/viewreports capability
// (e.g. manager role) to see these pages in the admin navigation.
$_ctx = context_system::instance();
$_can_manage  = $hassiteconfig
    || (!isguestuser() && isloggedin()
        && has_capability('local/subscriptions:manage', $_ctx, null, false));
$_can_report  = $hassiteconfig
    || (!isguestuser() && isloggedin()
        && has_capability('local/subscriptions:viewreports', $_ctx, null, false));

if ($_can_manage) {
    // Admin page: Manage Plans.
    $ADMIN->add('users', new admin_externalpage(
        'local_subscriptions_admin',
        get_string('manage_plans', 'local_subscriptions'),
        new moodle_url('/local/subscriptions/admin/plans.php'),
        'local/subscriptions:manage'
    ));

    // Admin page: Assign a subscription manually.
    $ADMIN->add('users', new admin_externalpage(
        'local_subscriptions_assign',
        get_string('assign_subscription', 'local_subscriptions'),
        new moodle_url('/local/subscriptions/admin/assign.php'),
        'local/subscriptions:manage'
    ));
}

if ($_can_report) {
    // Admin page: Reports.
    $ADMIN->add('users', new admin_externalpage(
        'local_subscriptions_report',
        get_string('reports', 'local_subscriptions'),
        new moodle_url('/local/subscriptions/admin/report.php'),
        'local/subscriptions:viewreports'
    ));
}
