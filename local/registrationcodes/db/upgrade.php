<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_registrationcodes_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026071001) {
        $table = new xmldb_table('local_regcodes');

        // Add groupname column.
        $field = new xmldb_field('groupname', XMLDB_TYPE_CHAR, '100', null, false, null, null, 'prefix');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add index on groupname.
        $index = new xmldb_index('idx_groupname', XMLDB_INDEX_NOTUNIQUE, ['groupname']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026071001, 'local', 'registrationcodes');
    }

    return true;
}
