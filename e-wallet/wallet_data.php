<?php
require('../config.php');
global $DB, $USER, $CFG;

header('Content-Type: application/json');

// Ensure user is logged in
if (!$USER->id) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

// Get wallet details for the logged-in user
$wallet = $DB->get_record('user_wallet', ['user_id' => $USER->id]);

if (!$wallet) {
    echo json_encode(['status' => 'error', 'message' => 'No wallet found']);
    exit;
}

$wallet_uuid = $wallet->wallet_uuid;
$api_key = "8b5a0e6d266ae2c3250a98ac3a568a95";
$api_url = "https://salem-mar3y.com/e-wallet/src/api/get_wallet_details.php";
$post_fields = json_encode(['wallet_uuid' => $wallet_uuid]);
$headers = [
    "Authorization: Bearer $api_key",
    "Content-Type: application/json"
];

// Initialize cURL session
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
curl_close($ch);

$wallet_data = json_decode($response, true);

if ($wallet_data['status'] == 'success') {
    echo json_encode(['status' => 'success', 'data' => $wallet_data['data']]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve wallet details']);
}
?>
