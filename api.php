<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// --- CONFIGURATION ---
$db_host = 'localhost';
$db_name = 'otoexpre_userdb';     
$db_user = 'otoexpre_userdb';     
$db_pass = 'p52DSsthB}=0AeZ#';     

// SERVICE ACCOUNT FILE PATH
$service_account_file = __DIR__ . '/service-account.json';

// --- DB CONNECTION ---
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500); 
    die(json_encode(['error' => 'DB Connection failed: ' . $e->getMessage()]));
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') exit(0);

function jsonResponse($data) {
    echo json_encode($data);
    exit;
}

function getJsonInput() {
    $input = json_decode(file_get_contents('php://input'), true);
    return (json_last_error() === JSON_ERROR_NONE) ? $input : [];
}

// --- HELPER: GET ACCESS TOKEN (V1) ---
function getAccessToken($keyFile) {
    if (!file_exists($keyFile)) return null;
    
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

function sendFCM_V1($pdo, $keyFile, $title, $body) {
    $stmt = $pdo->query("SELECT token FROM manager_tokens");
    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tokens)) return ['status' => 'no_tokens'];

    $accessToken = getAccessToken($keyFile);
    if (!$accessToken) return ['status' => 'auth_failed', 'msg' => 'Could not generate access token'];

    $keyData = json_decode(file_get_contents($keyFile), true);
    $projectId = $keyData['project_id'];
    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

    $results = [];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);

    foreach ($tokens as $token) {
        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
                'webpush' => [
                    'fcm_options' => [
                        'link' => 'https://portal.otoexpress.ge/'
                    ]
                ]
            ]
        ];
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $res = curl_exec($ch);
        $results[] = json_decode($res, true);
    }
    curl_close($ch);

    return $results;
}

try {
    // --- PUBLIC ACTIONS ---

    if ($action === 'get_public_transfer' && $method === 'GET') {
        $id = $_GET['id'] ?? 0;
        // Fetch status and review data
        $stmt = $pdo->prepare("SELECT id, name, plate, status, service_date as serviceDate, user_response as userResponse, review_stars as reviewStars, review_comment as reviewComment FROM transfers WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        jsonResponse($row ?: ['error' => 'Not found']);
    }

    if ($action === 'user_respond' && $method === 'POST') {
        $data = getJsonInput();
        $id = $data['id'] ?? 0;
        $response = $data['response'] ?? 'Confirmed';
        
        if($id) {
            $pdo->prepare("UPDATE transfers SET user_response = ? WHERE id = ?")->execute([$response, $id]);
            
            $stmt = $pdo->prepare("SELECT name, plate FROM transfers WHERE id = ?");
            $stmt->execute([$id]);
            $tr = $stmt->fetch();
            if($tr) {
                sendFCM_V1($pdo, $service_account_file, "Customer Responded", "{$tr['name']} ({$tr['plate']}) marked as: $response");
            }
        }
        jsonResponse(['status' => 'success']);
    }

    // --- NEW: SUBMIT REVIEW ---
    if ($action === 'submit_review' && $method === 'POST') {
        $data = getJsonInput();
        $id = $data['id'] ?? 0;
        $stars = $data['stars'] ?? 5;
        $comment = $data['comment'] ?? '';

        if($id) {
            $pdo->prepare("UPDATE transfers SET review_stars = ?, review_comment = ? WHERE id = ?")->execute([$stars, $comment, $id]);
            
            // Notify Manager
            $stmt = $pdo->prepare("SELECT name, plate FROM transfers WHERE id = ?");
            $stmt->execute([$id]);
            $tr = $stmt->fetch();
            if($tr) {
                sendFCM_V1($pdo, $service_account_file, "New 5-Star Review!", "{$tr['name']} rated: $stars Stars");
            }
        }
        jsonResponse(['status' => 'success']);
    }

    // --- MANAGER ACTIONS ---

    if ($action === 'get_transfers' && $method === 'GET') {
        // Includes review columns
        $stmt = $pdo->query("SELECT *, user_response as user_response, review_stars as reviewStars, review_comment as reviewComment FROM transfers ORDER BY created_at DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['internalNotes'] = json_decode($row['internalNotes'] ?? '[]');
            $row['systemLogs'] = json_decode($row['systemLogs'] ?? '[]');
            $row['serviceDate'] = $row['service_date']; 
        }
        jsonResponse($rows);
    }

    // ... (Rest of existing actions: add_transfer, update_transfer, delete_transfer, etc. remain unchanged) ...
    // Keeping previous endpoints for brevity, assume they are present here exactly as before.
    
    // 4. UPDATE EXISTING TRANSFER
    if ($action === 'update_transfer' && $method === 'POST') {
        $data = getJsonInput();
        $id = $_GET['id'] ?? 0;
        $fields = []; $params = [':id' => $id];
        foreach ($data as $key => $val) {
            if (in_array($key, ['phone', 'serviceDate', 'franchise', 'status', 'operatorComment', 'user_response'])) {
                if ($key === 'serviceDate') {
                    if(empty($val)) $val = null;
                    $fields[] = "service_date = :serviceDate";
                    $params[":serviceDate"] = $val;
                } else {
                    $fields[] = "$key = :$key";
                    $params[":$key"] = $val;
                }
            }
            if ($key === 'internalNotes' || $key === 'systemLogs') {
                $fields[] = "$key = :$key";
                $params[":$key"] = json_encode($val);
            }
        }
        if (!empty($fields)) {
            $pdo->prepare("UPDATE transfers SET " . implode(', ', $fields) . " WHERE id = :id")->execute($params);
        }
        jsonResponse(['status' => 'success']);
    }
    
    // ... Include get_vehicles, sync_vehicle, etc. from previous version ...
    if ($action === 'add_transfer' && $method === 'POST') {
        $data = getJsonInput();
        $sql = "INSERT INTO transfers (plate, name, amount, status, phone, rawText, internalNotes, systemLogs, user_response) VALUES (:plate, :name, :amount, 'New', '', :rawText, '[]', '[]', 'Pending')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':plate' => $data['plate'] ?? '', ':name' => $data['name'] ?? '', ':amount' => $data['amount'] ?? 0, ':rawText' => $data['rawText'] ?? '']);
        jsonResponse(['id' => $pdo->lastInsertId(), 'status' => 'success']);
    }
    if ($action === 'delete_transfer' && $method === 'POST') {
        $pdo->prepare("DELETE FROM transfers WHERE id = ?")->execute([$_GET['id'] ?? 0]);
        jsonResponse(['status' => 'deleted']);
    }
    if ($action === 'get_vehicles' && $method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM vehicles ORDER BY plate ASC");
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    if ($action === 'sync_vehicle' && $method === 'POST') {
        $data = getJsonInput();
        $plate = $data['plate'] ?? '';
        if ($plate) {
            $stmt = $pdo->prepare("SELECT id, ownerName, phone FROM vehicles WHERE plate = ?");
            $stmt->execute([$plate]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $updates = []; $params = [':id' => $existing['id']];
                if (!empty($data['phone']) && $data['phone'] !== $existing['phone']) { $updates[] = "phone = :phone"; $params[':phone'] = $data['phone']; }
                if (!empty($data['ownerName']) && empty($existing['ownerName'])) { $updates[] = "ownerName = :ownerName"; $params[':ownerName'] = $data['ownerName']; }
                if (!empty($updates)) $pdo->prepare("UPDATE vehicles SET " . implode(', ', $updates) . " WHERE id = :id")->execute($params);
            } else {
                $pdo->prepare("INSERT INTO vehicles (plate, ownerName, phone) VALUES (?, ?, ?)")->execute([$plate, $data['ownerName'] ?? '', $data['phone'] ?? '']);
            }
        }
        jsonResponse(['status' => 'synced']);
    }
    if ($action === 'save_vehicle' && $method === 'POST') {
        $data = getJsonInput();
        if (isset($_GET['id']) && $_GET['id']) {
            $pdo->prepare("UPDATE vehicles SET plate=?, ownerName=?, phone=?, model=? WHERE id=?")->execute([$data['plate'], $data['ownerName'], $data['phone'], $data['model'], $_GET['id']]);
        } else {
            $pdo->prepare("INSERT INTO vehicles (plate, ownerName, phone, model) VALUES (?, ?, ?, ?)")->execute([$data['plate'], $data['ownerName'], $data['phone'], $data['model']]);
        }
        jsonResponse(['status' => 'saved']);
    }
    if ($action === 'delete_vehicle' && $method === 'POST') {
        $pdo->prepare("DELETE FROM vehicles WHERE id=?")->execute([$_GET['id']]);
        jsonResponse(['status' => 'deleted']);
    }
    if ($action === 'send_sms' && $method === 'POST') {
        $data = getJsonInput();
        $to = $data['to'] ?? ''; $text = $data['text'] ?? '';
        if (empty($to) || empty($text)) jsonResponse(['status' => 'error', 'message' => 'Missing data']);
        $api_key = "5c88b0316e44d076d4677a4860959ef71ce049ce704b559355568a362f40ade1";
        echo @file_get_contents("https://api.gosms.ge/api/sendsms?api_key=$api_key&to=$to&from=OTOMOTORS&text=" . urlencode($text));
        exit;
    }
    if ($action === 'register_token' && $method === 'POST') {
        $data = getJsonInput();
        $token = $data['token'] ?? '';
        if ($token) {
            $stmt = $pdo->prepare("SELECT id FROM manager_tokens WHERE token = ?");
            $stmt->execute([$token]);
            if ($stmt->rowCount() == 0) {
                $pdo->prepare("INSERT INTO manager_tokens (token) VALUES (?)")->execute([$token]);
            }
        }
        jsonResponse(['status' => 'registered']);
    }
    if ($action === 'send_broadcast' && $method === 'POST') {
        $data = getJsonInput();
        $title = $data['title'] ?? 'New Notification';
        $body = $data['body'] ?? '';
        $result = sendFCM_V1($pdo, $service_account_file, $title, $body);
        jsonResponse(['status' => 'sent', 'fcm_result' => $result]);
    }
    if ($action === 'get_templates' && $method === 'GET') {
        $stmt = $pdo->query("SELECT slug, content FROM sms_templates");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        jsonResponse($rows ?: new stdClass());
    }
    if ($action === 'save_templates' && $method === 'POST') {
        $data = getJsonInput();
        $stmt = $pdo->prepare("INSERT INTO sms_templates (slug, content) VALUES (:slug, :content) ON DUPLICATE KEY UPDATE content = :content");
        foreach ($data as $slug => $content) {
            $stmt->execute([':slug' => $slug, ':content' => $content]);
        }
        jsonResponse(['status' => 'saved']);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>