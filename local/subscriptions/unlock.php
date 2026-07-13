<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint: unlock a lesson against the current user's active subscription.
 *
 * Expects POST: cmid, sesskey. Responds with JSON {status, message, remaining}.
 *
 * @package    local_subscriptions
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

use local_subscriptions\manager;

require_login();

header('Content-Type: application/json; charset=utf-8');

$response = ['status' => 'error', 'message' => '', 'remaining' => 0];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new \moodle_exception('invalidrequest');
    }
    require_sesskey();

    if (isguestuser()) {
        throw new \moodle_exception('noguest');
    }

    $cmid = required_param('cmid', PARAM_INT);

    $response = manager::unlock_lesson((int)$USER->id, $cmid);

} catch (\moodle_exception $e) {
    $response = ['status' => 'error', 'message' => $e->getMessage(), 'remaining' => 0];
} catch (\Throwable $e) {
    $response = ['status' => 'error', 'message' => 'error', 'remaining' => 0];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
