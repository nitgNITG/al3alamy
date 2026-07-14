<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Upgrade steps for local_deviceregistration.
 *
 * @package    local_deviceregistration
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion the version we are upgrading from.
 * @return bool
 */
function xmldb_local_deviceregistration_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // ── 2026071300: add the registered-devices table (enforcement story) ──────
    if ($oldversion < 2026071300) {
        $table = new xmldb_table('local_devreg_device');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('devicetoken',  XMLDB_TYPE_CHAR,    '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('useragent',    XMLDB_TYPE_CHAR,    '255', null, null, null, null);
            $table->add_field('lastip',       XMLDB_TYPE_CHAR,    '45', null, null, null, null);
            $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timelastseen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('user_token_unique', XMLDB_KEY_UNIQUE, ['userid', 'devicetoken']);
            $table->add_index('idx_userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026071300, 'local', 'deviceregistration');
    }

    return true;
}
