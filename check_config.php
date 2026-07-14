<?php
require_once(__DIR__ . '/config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$enabled = get_config('local_deviceregistration', 'enabled');
$max = get_config('local_deviceregistration', 'maxdevices');

echo "<h1>Device Registration Settings in Database</h1>";
echo "<p>Enabled: " . var_export($enabled, true) . "</p>";
echo "<p>Max Devices: " . var_export($max, true) . "</p>";
