<?php
require_once(__DIR__ . '/config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

purge_all_caches();
echo "<h1>Caches successfully purged!</h1><p>Moodle has reloaded all files and event observers.</p><p>You can now test the second device login.</p>";
