<?php

// Include necessary Moodle files
require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/course/externallib.php');
require_once($CFG->dirroot . '/user/externallib.php');
require_once($CFG->libdir . "/weblib.php");
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/theme/edumy/ccn/block_handler/ccn_block_handler.php');
require_once($CFG->dirroot . '/course/renderer.php');
require_once($CFG->dirroot . '/theme/edumy/ccn/course_handler/ccn_course_handler.php');
require_once($CFG->dirroot . '/theme/edumy/ccn/user_handler/ccn_user_handler.php');

global $DB, $CFG;

$EX = $_GET['ex_date'];
$id = $_GET['user_id'];

// Sanitize the user input and use parameter binding
$EX = clean_param($EX, PARAM_TEXT);
$id = clean_param($id, PARAM_INT);

// Construct the SQL query
$sql = "UPDATE {user}
        SET ex_date = :ex_date
        WHERE id = :user_id";

$params = array('ex_date' => $EX, 'user_id' => $id);

// Execute the update query
$success = $DB->execute($sql, $params);

if ($success) {
    header('Location: /new');
    exit;
    //print_r('ex_date: ' . $EX . " // user_id: " . $id . " updated successfully");
} else {
    // Redirect to a new page with an error message
    $error_message = "Failed to update ex_date"; // Set your error message here
    $url = "/new?error=" . urlencode($error_message); // Include the error message in the URL

    header('Location: ' . $url); // Redirect to the new page with the error message
    exit;
}
