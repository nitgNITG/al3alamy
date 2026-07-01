<?php
require_once("config.php");
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/enrol/externallib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

header('Content-Type: application/json');

try {
    // Check if the request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method. Only POST requests are accepted.']);
        exit;
    }

    // Validate required POST data
    if (empty($_POST['code']) || empty($_POST['userid']) || empty($_POST['courseid']) || empty($_POST['groupid'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required data. Please provide a valid code, userid, courseid, and groupid.']);
        exit;
    }

    // Check if the code exists and is not used
    $code = $DB->get_record('groups_attendence_codes', ['code' => $_POST['code']]);
    if (!$code || $code->used == 1) {
        echo json_encode(['status' => 'error', 'message' => 'This code does not exist or has been used before.']);
        exit;
    }

    // Get code patch
    $patch = $DB->get_record('groups_attendence_patch', ['id' => $code->patchid, 'courseid' => $_POST['courseid']]);
    if (!$patch) {
        echo json_encode(['status' => 'error', 'message' => 'This code does not belong to this course or group.']);
        exit;
    }

    // Validate group ID
    if ($patch->groupid > 0 && $patch->groupid !== $_POST['groupid']) {
        echo json_encode(['status' => 'error', 'message' => 'This code does not belong to the specified group.']);
        exit;
    }
    $groupid = $patch->groupid > 0 ? $patch->groupid : $_POST['groupid'];

    // Check if user is already in the group
    if ($DB->record_exists('groups_members', ['userid' => $_POST['userid'], 'groupid' => $groupid])) {
        echo json_encode(['status' => 'error', 'message' => 'User is already a member of this group.']);
        exit;
    }

    // Add user to group
    if (groups_add_member($groupid, $_POST['userid'])) {
        // Update code data
        $code->used = 1;
        $code->empty1 = $groupid;
        $code->empty2 = $_POST['userid'];
        $DB->update_record('groups_attendence_codes', $code);

        echo json_encode(['status' => 'success', 'message' => 'User successfully added to the group.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add user to group. Please try again later.']);
    }
} catch (Exception $e) {
    // Handle errors and output them as JSON
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
