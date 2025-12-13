<?php
// Test SMS sending functionality
require_once 'config.php';

echo "<h1>SMS Sending Test</h1>";

// Test data
$testPhone = "995551234567"; // Replace with a real test number
$testMessage = "Test SMS from OTOMOTORS portal - " . date('Y-m-d H:i:s');

echo "<p>Testing SMS to: $testPhone</p>";
echo "<p>Message: $testMessage</p>";

// Simulate the API call
$url = "https://api.gosms.ge/api/sendsms?api_key=" . SMS_API_KEY . "&to=$testPhone&from=OTOMOTORS&text=" . urlencode($testMessage);

echo "<p>API URL: $url</p>";

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'OTOMOTORS Portal Test'
    ]
]);

$response = @file_get_contents($url, false, $context);

if ($response === false) {
    $error = error_get_last();
    echo "<p style='color: red;'>FAILED: HTTP request failed - " . ($error['message'] ?? 'Unknown error') . "</p>";
} else {
    echo "<p>API Response: $response</p>";

    if (strpos($response, '<result>1</result>') !== false ||
        strpos($response, 'success') !== false ||
        strpos($response, '<status>success</status>') !== false) {
        echo "<p style='color: green;'>SUCCESS: SMS sent successfully!</p>";
    } else {
        echo "<p style='color: red;'>FAILED: API response indicates failure</p>";
    }
}

// Also test the API endpoint
echo "<hr><h2>Testing API Endpoint</h2>";
echo "<p>Testing the send_sms endpoint...</p>";

$apiData = json_encode([
    'to' => $testPhone,
    'text' => $testMessage
]);

$options = [
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => $apiData,
        'timeout' => 10
    ]
];

$context = stream_context_create($options);
$apiResponse = @file_get_contents('api.php?action=send_sms', false, $context);

if ($apiResponse === false) {
    echo "<p style='color: red;'>API call failed</p>";
} else {
    echo "<p>API Response: $apiResponse</p>";
    $decoded = json_decode($apiResponse, true);
    if ($decoded && isset($decoded['status'])) {
        if ($decoded['status'] === 'success') {
            echo "<p style='color: green;'>API SUCCESS: " . $decoded['message'] . "</p>";
        } else {
            echo "<p style='color: red;'>API ERROR: " . $decoded['message'] . "</p>";
        }
    }
}
?>