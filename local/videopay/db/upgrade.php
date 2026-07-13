<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_videopay_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026071301) {
        $table = new xmldb_table('local_videopay_prices');
        $field = new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0', 'is_free');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026071301, 'local', 'videopay');
    }

    return true;
}
