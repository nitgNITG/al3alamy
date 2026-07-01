<?php
require('../config.php');
global $DB, $USER, $CFG;

// API details
$platform_uuid = "17b931f8-5a3e-11ef-b921-005056472f78";
$api_key = "8b5a0e6d266ae2c3250a98ac3a568a95";

// Create a new wallet using the API
$api_url = "https://salem-mar3y.com/e-wallet/src/api/create_wallet.php";
$post_fields = json_encode(array("platform_uuid" => $platform_uuid));
$headers = array(
    "Authorization: Bearer $api_key",
    "Content-Type: application/json"
);

// Initialize cURL session
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
curl_close($ch);

$wallet_creation_data = json_decode($response, true);

if ($wallet_creation_data['status'] == "success") {
    $new_wallet_uuid = $wallet_creation_data['data']['wallet_uuid'];

    // Save the new wallet details to the "user_wallet" table
    $new_wallet_record = new stdClass();
    $new_wallet_record->user_id = $USER->id;
    $new_wallet_record->wallet_uuid = $new_wallet_uuid;
    $DB->insert_record('user_wallet', $new_wallet_record);

    echo json_encode(array('status' => 'success', 'message' => 'Wallet created successfully.'));
} else {
    echo json_encode(array('status' => 'error', 'message' => 'Failed to create a new wallet.'));
}
?>
