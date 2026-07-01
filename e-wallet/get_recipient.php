<?php
require('../config.php');
global $DB, $USER;

header('Content-Type: application/json');

// Ensure the user is logged in
if (!$USER->id) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

function upload_user_image($id)
{
    global $DB, $CFG;
    $user = $DB->get_record('user', array('id' => $id));
    $user_context = $DB->get_record('context', array('instanceid' => $id, 'contextlevel' => 30));
    $fs = get_file_storage();
    $files = $fs->get_area_files($user_context->id, 'user', 'icon', 0, 'sortorder DESC, id ASC', false);
    if (count($files) < 1) {
        $image = '' . $CFG->wwwroot . '/pluginfile.php/' . $user_context->id . '/user/icon/0/f1.jpg?rev=0';
    } else {
        $file = reset($files);
        unset($files);
        $path = '/' . $user_context->id . '/user/icon/0' . $file->get_filepath() . $file->get_filename();
        $image = $CFG->wwwroot . '/pluginfile.php' . $path . "?rev=" . $user->picture;
    }
    return $image;
}

// Check if UUID is provided
if (isset($_POST['uuid'])) {
    $uuid = $_POST['uuid'];

    // Get recipientID from the database using Moodle's DB API
    $recipientID = $DB->get_field('user_wallet', 'user_id', array('wallet_uuid' => $uuid));

    if ($recipientID) {
        // Get user details
        $user = $DB->get_record('user', array('id' => $recipientID), 'firstname, lastname');

        if ($user) {
            $username = $user->firstname . ' ' . $user->lastname;
            $imgurl = upload_user_image($USER->id);

            // Return recipientID and username as JSON
            echo json_encode([
                'status' => 'success',
                'imgurl' => $imgurl,
                'username' => $username
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'User not found']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid UUID']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'UUID not provided']);
}
