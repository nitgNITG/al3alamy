<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Capabilities for local_registrationcodes.
 */
$capabilities = [

    // Generate new registration codes.
    'local/registrationcodes:generate' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Manage existing codes (enable, disable, delete).
    'local/registrationcodes:manage' => [
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // View registration code reports.
    'local/registrationcodes:viewreports' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Delete unused codes.
    'local/registrationcodes:delete' => [
        'riskbitmask' => RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
