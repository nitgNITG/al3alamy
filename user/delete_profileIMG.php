<?php
require_once('../config.php');

$user_id = $_GET['user_id'];

// Get the image path
$profile_img = $DB->get_field('teacher_profile_img', 'image_path', array('user_id' => $USER->id));

if ($profile_img) {
    // Delete the image file from the directory
    unlink($profile_img);

    // Delete the image record from the database
    $success = $DB->delete_records('teacher_profile_img', array('user_id' => $USER->id));

    if ($success) {
        // Redirect to profile.php after successful deletion
        header('Location: profile.php');
        exit(); // Make sure to exit after redirection
    } else {
        echo 'Fail to delete this image!';
    }
}
?>
