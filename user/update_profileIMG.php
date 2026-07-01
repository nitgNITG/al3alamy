<?php
require_once('../config.php');

if (isset($_FILES["new_profile_img"]) && $_FILES["new_profile_img"]["error"] === UPLOAD_ERR_OK) {
    $newImagePath = '../service_images/' . basename($_FILES["new_profile_img"]["name"]);

    $user_id = $_GET['user_id'];

    // Move the uploaded image to the desired location
    if (move_uploaded_file($_FILES["new_profile_img"]["tmp_name"], $newImagePath)) {

        $data = new stdClass();
        $data->user_id = $USER->id; 
        $data->image_path = $newImagePath;
        $record = $DB->insert_record('teacher_profile_img', $data);

        // Return the new image path as a response
        echo $newImagePath;
    }
}
?>
