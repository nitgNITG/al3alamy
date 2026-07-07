<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Add a category under "Users".
    $ADMIN->add('users', new admin_category(
        'local_registrationcodes_cat',
        get_string('pluginname_nav', 'local_registrationcodes')
    ));

    // Manage codes page.
    $ADMIN->add('local_registrationcodes_cat', new admin_externalpage(
        'local_registrationcodes_admin',
        get_string('manage_codes', 'local_registrationcodes'),
        new moodle_url('/local/registrationcodes/admin.php'),
        ['local/registrationcodes:manage', 'local/registrationcodes:generate']
    ));

    // Usage reports page.
    $ADMIN->add('local_registrationcodes_cat', new admin_externalpage(
        'local_registrationcodes_report',
        get_string('reports', 'local_registrationcodes'),
        new moodle_url('/local/registrationcodes/report.php'),
        'local/registrationcodes:viewreports'
    ));
}
