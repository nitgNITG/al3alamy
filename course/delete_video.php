<?php
// delete_video.php
require_once('../config.php');

if (isset($_GET["id"]) && isset($_GET["course_id"])) {
    $videoId = $_GET["id"];
    $courseId = $_GET["course_id"];

    // Fetch the video URL from the database
    global $DB;
    $videoRecord = $DB->get_record('course_promo_videos', array('id' => $videoId));

    if ($videoRecord) {
        // Delete the video file from the directory
        $videoPath = "course_promo_videos/" . $videoRecord->url_name;
        if (file_exists($videoPath)) {
            unlink($videoPath);
        }

        // Delete the video record from the database
        $DB->delete_records('course_promo_videos', array('id' => $videoId));

        // Redirect back to the edit page
        header('Location: edit.php?id=' . $courseId);
        exit();
    } else {
        echo "Video not found.";
    }
} else {
    echo "Invalid parameters.";
}
?>
