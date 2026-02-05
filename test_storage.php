<?php
/**
 * Test Firebase Storage Authentication
 * Access this file directly in browser to see detailed debug output
 */

require_once 'session_config.php';
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Firebase Storage Authentication Test</h1>";
echo "<pre>";

$keyFile = __DIR__ . '/service-account.json';

echo "1. Checking service account file...\n";
if (!file_exists($keyFile)) {
    echo "   ❌ ERROR: service-account.json not found!\n";
    exit;
}
echo "   ✅ File exists\n\n";

echo "2. Reading service account...\n";
$keyData = json_decode(file_get_contents($keyFile), true);
if (!$keyData) {
    echo "   ❌ ERROR: Could not parse JSON\n";
    exit;
}
echo "   ✅ JSON parsed\n";
echo "   Project ID: " . ($keyData['project_id'] ?? 'MISSING') . "\n";
echo "   Client Email: " . ($keyData['client_email'] ?? 'MISSING') . "\n";
echo "   Has Private Key: " . (!empty($keyData['private_key']) ? 'Yes' : 'No') . "\n\n";

echo "3. Testing OpenSSL signing...\n";
$testData = "test";
$signature = '';
$signResult = openssl_sign($testData, $signature, $keyData['private_key'], 'SHA256');
if (!$signResult) {
    echo "   ❌ ERROR: OpenSSL signing failed\n";
    echo "   OpenSSL Error: " . openssl_error_string() . "\n";
    exit;
}
echo "   ✅ OpenSSL signing works\n\n";

echo "4. Creating JWT for storage scope...\n";
$scope = 'https://www.googleapis.com/auth/devstorage.full_control';
$header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
$now = time();
$claim = json_encode([
    'iss' => $keyData['client_email'],
    'scope' => $scope,
    'aud' => 'https://oauth2.googleapis.com/token',
    'exp' => $now + 3600,
    'iat' => $now
]);

$base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
$base64UrlClaim = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($claim));
$signatureInput = $base64UrlHeader . "." . $base64UrlClaim;

$signature = '';
openssl_sign($signatureInput, $signature, $keyData['private_key'], 'SHA256');
$base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
$jwt = $signatureInput . "." . $base64UrlSignature;

echo "   ✅ JWT created (length: " . strlen($jwt) . ")\n\n";

echo "5. Requesting OAuth token...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
    'assertion' => $jwt
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlError) {
    echo "   ❌ cURL Error: $curlError\n";
    exit;
}

echo "   HTTP Code: $httpCode\n";

$tokenData = json_decode($response, true);
if (isset($tokenData['access_token'])) {
    echo "   ✅ Got access token!\n";
    echo "   Token (first 50 chars): " . substr($tokenData['access_token'], 0, 50) . "...\n\n";
    
    echo "6. Testing upload to Firebase Storage...\n";
    $projectId = $keyData['project_id'];
    $storageBucket = $projectId . '.firebasestorage.app';
    $testFilename = "test/auth_test_" . time() . ".txt";
    $testContent = "Test upload at " . date('Y-m-d H:i:s');
    
    $uploadUrl = "https://storage.googleapis.com/upload/storage/v1/b/{$storageBucket}/o?uploadType=media&name=" . urlencode($testFilename);
    
    echo "   Bucket: $storageBucket\n";
    echo "   Upload URL: $uploadUrl\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $uploadUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $testContent);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $tokenData['access_token'],
        'Content-Type: text/plain',
        'Content-Length: ' . strlen($testContent)
    ]);
    
    $uploadResponse = curl_exec($ch);
    $uploadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $uploadCurlError = curl_error($ch);
    curl_close($ch);
    
    echo "   Upload HTTP Code: $uploadHttpCode\n";
    
    if ($uploadCurlError) {
        echo "   ❌ cURL Error: $uploadCurlError\n";
    } elseif ($uploadHttpCode === 200) {
        echo "   ✅ Upload successful!\n";
        $uploadData = json_decode($uploadResponse, true);
        echo "   File: " . ($uploadData['name'] ?? 'unknown') . "\n";
        $downloadUrl = "https://firebasestorage.googleapis.com/v0/b/{$storageBucket}/o/" . urlencode($testFilename) . "?alt=media";
        echo "   Download URL: $downloadUrl\n";
    } else {
        echo "   ❌ Upload failed!\n";
        echo "   Response: $uploadResponse\n";
    }
} else {
    echo "   ❌ Failed to get access token\n";
    echo "   Error: " . ($tokenData['error'] ?? 'unknown') . "\n";
    echo "   Description: " . ($tokenData['error_description'] ?? $response) . "\n";
}

echo "</pre>";
?>
