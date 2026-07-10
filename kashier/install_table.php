<?php
/**
 * Run this once on the server to create the kashier_transactions table.
 * Access via browser: https://al3alamy.com/kashier/install_table.php
 * Then DELETE this file immediately after running.
 */

require_once(__DIR__ . '/../config.php');

global $DB, $CFG;

// Only site admins.
require_login();
require_capability('moodle/site:config', context_system::instance());

$dbman = $DB->get_manager();

$table = new xmldb_table('kashier_transactions');
$table->add_field('id',             XMLDB_TYPE_INTEGER, '10',    true, XMLDB_NOTNULL, XMLDB_SEQUENCE);
$table->add_field('order_id',       XMLDB_TYPE_CHAR,    '120',   false, XMLDB_NOTNULL);
$table->add_field('transaction_id', XMLDB_TYPE_CHAR,    '120',   false, null);
$table->add_field('user_id',        XMLDB_TYPE_INTEGER, '10',    false, XMLDB_NOTNULL);
$table->add_field('amount',         XMLDB_TYPE_NUMBER,  '10,2',  false, XMLDB_NOTNULL);
$table->add_field('currency',       XMLDB_TYPE_CHAR,    '10',    false, XMLDB_NOTNULL, null, 'EGP');
$table->add_field('type',           XMLDB_TYPE_CHAR,    '20',    false, XMLDB_NOTNULL, null, 'video');
$table->add_field('status',         XMLDB_TYPE_CHAR,    '20',    false, XMLDB_NOTNULL, null, 'pending');
$table->add_field('timecreated',    XMLDB_TYPE_INTEGER, '10',    false, XMLDB_NOTNULL);
$table->add_key('primary',          XMLDB_KEY_PRIMARY,  ['id']);
$table->add_index('order_id',       XMLDB_INDEX_UNIQUE, ['order_id']);
$table->add_index('user_id',        XMLDB_INDEX_NOTUNIQUE, ['user_id']);

if (!$dbman->table_exists($table)) {
    $dbman->create_table($table);
    echo '<p style="color:green;font-family:monospace;">✅ Table <strong>mdl_kashier_transactions</strong> created successfully.</p>';
} else {
    echo '<p style="color:orange;font-family:monospace;">⚠️ Table already exists — nothing done.</p>';
}

echo '<p style="color:red;font-family:monospace;"><strong>DELETE this file from the server now!</strong></p>';
