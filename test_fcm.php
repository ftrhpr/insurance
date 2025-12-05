<?php
// Run this file: https://your-site.com/test_fcm.php

$db_host = 'localhost';
$db_name = 'otoexpre_userdb';     
$db_user = 'otoexpre_userdb';     
$db_pass = 'p52DSsthB}=0AeZ#';     
$keyFile = __DIR__ . '/service-account.json';

// 1. Check Service Account
if (!file_exists($keyFile)) {
    die("❌ Error: 'service-account.json' not found. Please upload it.");
} else {
    echo "✅ Service Account File: Found<br>";
}

// 2. DB Connection
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    echo "✅ Database Connection: Success<br>";
} catch (PDOException $e) {
    die("❌ Database Connection Failed: " . $e->getMessage());
}

// 3. Get Tokens
$stmt = $pdo->query("SELECT token FROM manager_tokens");
$tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "🔎 Found " . count($tokens) . " tokens.<br>";

if (empty($tokens)) die("❌ No tokens found. Enable notifications in the app first.");

// 4. Generate Access Token
function getTestToken($keyFile) {
    $keyData = json_decode(file_get_contents($keyFile), true);
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $now = time();
    $claim = json_encode([
        'iss' => $keyData['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
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

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $tokenData = json_decode($response, true);
    return $tokenData['access_token'] ?? null;
}

$accessToken = getTestToken($keyFile);
if (!$accessToken) die("❌ Failed to generate OAuth2 Access Token. Check service-account.json permissions.");
echo "✅ OAuth2 Token Generated.<br>";

// 5. Send Notification
$keyData = json_decode(file_get_contents($keyFile), true);
$projectId = $keyData['project_id'];
$url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

echo "<h3>Sending Tests...</h3>";

foreach ($tokens as $token) {
    $payload = [
        'message' => [
            'token' => $token,
            'notification' => [
                'title' => 'V1 API Test',
                'body' => 'Success! V1 API is working.'
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $result = curl_exec($ch);
    echo "Token " . substr($token, 0, 10) . "... : <pre>" . htmlspecialchars($result) . "</pre><hr>";
    curl_close($ch);
}
?>