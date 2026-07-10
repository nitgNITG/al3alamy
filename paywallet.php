<?php
require_once("config.php");
// require_once('twoteachers/academyApi/json.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/enrol/externallib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

try {
    // Check if the request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method. Only POST requests are accepted.');
    }

    // Validate required POST data
    if (empty($_POST['groupid']) || empty($_POST['courseid']) || empty($_POST['userid'])) {
        throw new Exception('Missing required data. Please provide a valid groupid, userid, and courseid.');
    }

    // Check if user is already in the group
    if ($DB->record_exists('groups_members', array('userid' => $_POST['userid'], 'groupid' => $_POST['groupid']))) {
        throw new Exception('User is already a member of this group.');
    }

    if (!$DB->record_exists('user_wallet', array('user_id' => $_POST['userid']))) {
        throw new Exception('User dont have wallet.');
    }

    // Retrieve group and wallet information
    $group = $DB->get_record('groups', array('id' => $_POST['groupid']));
    if (!$group) {
        throw new Exception('Group not found.');
    }
    $group_price = $group->description; // Assuming group price is stored in the description field
    $wallet_uuid = $DB->get_field('user_wallet', 'wallet_uuid', array('user_id' => $_POST['userid']));

    // Prepare data for API call
    $api_url = 'https://xmathsacademy.com/e-wallet/src/api/pay_wallet.php';
    $api_token = '8b5a0e6d266ae2c3250a98ac3a568a95';

    $data = array(
        'wallet_uuid' => $wallet_uuid,
        'amount' => $group_price,
        'description' => "Payment for: {$group->name}"
    );

    // Initialize cURL
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_token
    ));

    // Execute API request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Handle API response
    if ($http_code !== 200) {
        throw new Exception('Payment API request failed with status code ' . $http_code);
    }

    $response_data = json_decode($response, true);
    if (!$response_data || $response_data['status'] !== 'success') {
        throw new Exception('Payment failed: ' . ($response_data['message'] ?? 'Unknown error'));
    }

    // Add user to group (if payment was successful)
    if (!groups_add_member($_POST['groupid'], $_POST['userid'])) {
        throw new Exception('Failed to add user to group.');
    }

    // ── Enroll user in the course via the manual enrolment plugin ──────────
    $courseid = (int)$_POST['courseid'];
    $userid   = (int)$_POST['userid'];

    $course_context = context_course::instance($courseid);

    if (!is_enrolled($course_context, $userid)) {
        // Find the enabled manual enrol instance for this course
        $enrol_instance = $DB->get_record('enrol', [
            'courseid' => $courseid,
            'enrol'    => 'manual',
            'status'   => 0,   // ENROL_INSTANCE_ENABLED
        ]);

        if ($enrol_instance) {
            $enrol_plugin  = enrol_get_plugin('manual');
            $student_role  = $DB->get_record('role', ['shortname' => 'student']);
            $roleid        = $student_role ? (int)$student_role->id : 5; // fallback to default student role id
            $enrol_plugin->enrol_user($enrol_instance, $userid, $roleid);
        } else {
            // No manual instance — log a warning but don't fail the payment
            error_log("paywallet.php: no enabled manual enrol instance for course $courseid; user $userid was NOT enrolled.");
        }
    }
    // ────────────────────────────────────────────────────────────────────────

    // Return success response
    header('Content-Type: application/json'); // Ensure the content type is set to JSON
    $response_data = ['status' => 'success', 'message' => 'Payment successful. User enrolled and added to the group.'];
    echo json_encode($response_data);


    //echo 'userid: ' . $_POST['userid'];
    //echo '<br>';
    //echo 'courseid: ' . $_POST['courseid'];
    //echo '<br>';
    //echo 'groupid: ' . $_POST['groupid'];
    //echo '<br>';
    //echo 'group_price: ' . $group_price;
    //echo '<br>';
    //echo 'wallet_uuid: ' . $wallet_uuid;
} catch (Exception $e) {
    // Handle errors and output them as JSON
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
