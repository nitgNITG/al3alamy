<?php
defined('MOODLE_INTERNAL') || die();

// ── Site-admin path: free code generation + full management ───────────────────
if ($hassiteconfig) {
    $ADMIN->add('users', new admin_category(
        'local_registrationcodes_cat',
        get_string('pluginname_nav', 'local_registrationcodes')
    ));

    $ADMIN->add('local_registrationcodes_cat', new admin_externalpage(
        'local_registrationcodes_admin',
        get_string('manage_codes', 'local_registrationcodes'),
        new moodle_url('/local/registrationcodes/admin.php'),
        ['local/registrationcodes:manage', 'local/registrationcodes:generate']
    ));

    $ADMIN->add('local_registrationcodes_cat', new admin_externalpage(
        'local_registrationcodes_report',
        get_string('reports', 'local_registrationcodes'),
        new moodle_url('/local/registrationcodes/report.php'),
        'local/registrationcodes:viewreports'
    ));
}

// ── Manager path: paid code purchase (visible to non-admin managers) ──────────
if (!$hassiteconfig && !is_siteadmin()
    && has_capability('local/registrationcodes:generate', context_system::instance())) {

    $ADMIN->add('users', new admin_category(
        'local_registrationcodes_cat',
        get_string('pluginname_nav', 'local_registrationcodes')
    ));

    $ADMIN->add('local_registrationcodes_cat', new admin_externalpage(
        'local_registrationcodes_buy',
        get_string('buycodes_nav', 'local_registrationcodes'),
        new moodle_url('/local/registrationcodes/buy.php'),
        'local/registrationcodes:generate'
    ));
}
