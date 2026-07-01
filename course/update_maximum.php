<?php
require_once("../config.php");

global $DB, $OUTPUT, $CFG;

if (isset($_POST['maximumNumber'])) {
    $newMaximum = intval($_POST['maximumNumber']);

    $new = new stdClass();
    $new->id = 1;
    $new->count = $newMaximum;

    // Perform the database update
    $success = $DB->update_record('count_activities', $new);

    if ($success) {
        // Redirect to a new URL upon successful update
        header("Location: {$CFG->wwwroot}/course");
        exit; // Ensure no more code is executed after the header is sent
    } else {
        // Handle the case where the update was not successful
        echo "Update failed.";
    }
} else {
    echo "Invalid request.";
}
?>
