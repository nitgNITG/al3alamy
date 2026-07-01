<?php
require_once(__DIR__ . '/../config.php');

if (isset($_GET["user_id"])) {
    $user_id = $_GET["user_id"];
    $course_id = $_GET["course_id"];


    // تسجيل الطالب في الكورس
    $enrolid = $DB->get_field('enrol', 'id', array('courseid' => $course_id, 'enrol' => 'manual'));
    $enrolment = $DB->insert_record('user_enrolments', array('enrolid' => $enrolid, 'userid' => $user_id));

    $context_id = $DB->get_field('context', 'id', array('instanceid' => $course_id, 'contextlevel' => 50));
    $student_role = $DB->insert_record('role_assignments', array('roleid' => 5, 'contextid' => $context_id, 'userid' => $user_id));
    /********************************************************************************************************************************/

    // Update the confirmed status in the database
    $updateSuccess = $DB->update_record('user', array('id' => $user_id, 'confirmed' => 1));

    if ($updateSuccess) {
        echo "User authenticated successfully.";
        // Redirect to profile.php
        header('Location: profile.php');
        exit();
    } else {
        echo "Failed to authenticate user.";
    }
} else {
    echo "Invalid user ID.";
}
