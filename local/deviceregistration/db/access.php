<?php
/**
 * Capability definitions for local_deviceregistration.
 *
 * @package    local_deviceregistration
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Allows access to the Device Registration admin page (force-logout + device management).
    // Granted to managers by default; admins always have it.
    'local/deviceregistration:manage' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

];
