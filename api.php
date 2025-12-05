<?php
session_start();

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

// Check authentication for protected endpoints
$publicEndpoints = ['login', 'get_order_status', 'submit_review'];
if (!in_array($action, $publicEndpoints) && empty($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// Check role permissions
function checkPermission($required_role) {
    global $pdo;
    $user_role = $_SESSION['role'] ?? 'viewer';
    $hierarchy = ['viewer' => 1, 'manager' => 2, 'admin' => 3];
    return $hierarchy[$user_role] >= $hierarchy[$required_role];
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

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
        if (!$id) {
            jsonResponse(['error' => 'Missing ID parameter']);
        }
        
        // Fetch status and review data
        $stmt = $pdo->prepare("SELECT id, name, plate, status, service_date as serviceDate, user_response as userResponse, review_stars as reviewStars, review_comment as reviewComment FROM transfers WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            http_response_code(404);
            jsonResponse(['error' => 'Transfer not found', 'id' => $id]);
        }
        
        jsonResponse($row);
    }

    if ($action === 'user_respond' && $method === 'POST') {
        $data = getJsonInput();
        $id = $data['id'] ?? 0;
        $response = $data['response'] ?? 'Confirmed';
        $rescheduleDate = $data['reschedule_date'] ?? null;
        $rescheduleComment = $data['reschedule_comment'] ?? null;
        
        if($id) {
            // Update user response
            $pdo->prepare("UPDATE transfers SET user_response = ? WHERE id = ?")->execute([$response, $id]);
            
            // If reschedule request, store the desired date and comment
            if ($response === 'Reschedule Requested' && $rescheduleDate) {
                $pdo->prepare("UPDATE transfers SET reschedule_date = ?, reschedule_comment = ? WHERE id = ?")
                    ->execute([$rescheduleDate, $rescheduleComment, $id]);
            }
            
            $stmt = $pdo->prepare("SELECT name, plate FROM transfers WHERE id = ?");
            $stmt->execute([$id]);
            $tr = $stmt->fetch();
            if($tr) {
                $notificationBody = "{$tr['name']} ({$tr['plate']}) marked as: $response";
                if ($rescheduleDate) {
                    $notificationBody .= " - Requested: " . date('M d, Y H:i', strtotime($rescheduleDate));
                }
                sendFCM_V1($pdo, $service_account_file, "Customer Responded", $notificationBody);
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
            try {
                // Get transfer details
                $stmt = $pdo->prepare("SELECT name, plate FROM transfers WHERE id = ?");
                $stmt->execute([$id]);
                $tr = $stmt->fetch();
                
                if($tr) {
                    // Save to customer_reviews table
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $pdo->prepare("INSERT INTO customer_reviews (order_id, customer_name, rating, comment, status, ip_address) VALUES (?, ?, ?, ?, 'pending', ?)")
                        ->execute([$id, $tr['name'], $stars, $comment, $ip]);
                    
                    // Also update transfers table for backward compatibility
                    $pdo->prepare("UPDATE transfers SET review_stars = ?, review_comment = ? WHERE id = ?")->execute([$stars, $comment, $id]);
                    
                    // Notify Manager
                    sendFCM_V1($pdo, $service_account_file, "New Customer Review!", "{$tr['name']} ({$tr['plate']}) rated: $stars Stars");
                }
            } catch (PDOException $e) {
                // Table might not exist, just save to transfers table
                $pdo->prepare("UPDATE transfers SET review_stars = ?, review_comment = ? WHERE id = ?")->execute([$stars, $comment, $id]);
            }
        }
        jsonResponse(['status' => 'success']);
    }

    // --- ACCEPT RESCHEDULE REQUEST ---
    if ($action === 'accept_reschedule' && $method === 'POST') {
        $data = getJsonInput();
        $id = $_GET['id'] ?? 0;
        $serviceDate = $data['service_date'] ?? null;

        if ($id && $serviceDate) {
            // Update service date and clear reschedule request, mark as confirmed
            $pdo->prepare("UPDATE transfers SET service_date = ?, user_response = 'Confirmed', reschedule_date = NULL, reschedule_comment = NULL WHERE id = ?")
                ->execute([$serviceDate, $id]);

            // Get transfer details for SMS
            $stmt = $pdo->prepare("SELECT name, plate, phone, amount FROM transfers WHERE id = ?");
            $stmt->execute([$id]);
            $tr = $stmt->fetch();

            if ($tr && $tr['phone']) {
                // Send confirmation SMS
                $formattedDate = date('M d, Y H:i', strtotime($serviceDate));
                $smsText = "Hello {$tr['name']}, your reschedule request has been approved! New appointment: {$formattedDate}. Ref: {$tr['plate']}. - OTOMOTORS";
                
                $api_key = "5c88b0316e44d076d4677a4860959ef71ce049ce704b559355568a362f40ade1";
                $to = $tr['phone'];
                @file_get_contents("https://api.gosms.ge/api/sendsms?api_key=$api_key&to=$to&from=OTOMOTORS&text=" . urlencode($smsText));
            }

            jsonResponse(['status' => 'success', 'message' => 'Reschedule accepted and SMS sent']);
        } else {
            jsonResponse(['status' => 'error', 'message' => 'Invalid parameters']);
        }
    }

    // --- DECLINE RESCHEDULE REQUEST ---
    if ($action === 'decline_reschedule' && $method === 'POST') {
        $id = $_GET['id'] ?? 0;

        if ($id) {
            // Clear reschedule data and reset to pending
            $pdo->prepare("UPDATE transfers SET reschedule_date = NULL, reschedule_comment = NULL, user_response = 'Pending' WHERE id = ?")
                ->execute([$id]);

            jsonResponse(['status' => 'success', 'message' => 'Reschedule request declined']);
        } else {
            jsonResponse(['status' => 'error', 'message' => 'Invalid ID']);
        }
    }

    // --- MANAGER ACTIONS ---

    if ($action === 'get_transfers' && $method === 'GET') {
        // Includes review columns and reschedule data
        $stmt = $pdo->query("SELECT *, user_response as user_response, review_stars as reviewStars, review_comment as reviewComment, reschedule_date as rescheduleDate, reschedule_comment as rescheduleComment FROM transfers ORDER BY created_at DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['internalNotes'] = json_decode($row['internal_notes'] ?? '[]');
            $row['systemLogs'] = json_decode($row['system_logs'] ?? '[]');
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
                $dbColumn = $key === 'internalNotes' ? 'internal_notes' : 'system_logs';
                $fields[] = "$dbColumn = :$key";
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
        $sql = "INSERT INTO transfers (plate, name, amount, status, phone, rawText, internal_notes, system_logs, user_response) VALUES (:plate, :name, :amount, 'New', '', :rawText, '[]', '[]', 'Pending')";
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

    // --- CUSTOMER REVIEWS ENDPOINTS ---
    if ($action === 'get_reviews' && $method === 'GET') {
        try {
            $stmt = $pdo->query("SELECT * FROM customer_reviews ORDER BY created_at DESC");
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate statistics
            $total = count($reviews);
            $avgRating = 0;
            if ($total > 0) {
                $sum = array_sum(array_column($reviews, 'rating'));
                $avgRating = round($sum / $total, 1);
            }
            
            jsonResponse([
                'reviews' => $reviews,
                'total' => $total,
                'average_rating' => $avgRating
            ]);
        } catch (PDOException $e) {
            // Table might not exist yet
            jsonResponse([
                'reviews' => [],
                'total' => 0,
                'average_rating' => 0,
                'error' => 'Table not found. Please run fix_db_all.php'
            ]);
        }
    }

    if ($action === 'update_review_status' && $method === 'POST') {
        $data = getJsonInput();
        $id = $_GET['id'] ?? 0;
        $status = $data['status'] ?? 'pending';
        
        if ($id && in_array($status, ['pending', 'approved', 'rejected'])) {
            try {
                $pdo->prepare("UPDATE customer_reviews SET status = ? WHERE id = ?")->execute([$status, $id]);
                jsonResponse(['status' => 'success']);
            } catch (PDOException $e) {
                jsonResponse(['status' => 'error', 'message' => 'Table not found. Please run fix_db_all.php']);
            }
        } else {
            jsonResponse(['status' => 'error', 'message' => 'Invalid parameters']);
        }
    }

    // =====================================================
    // USER MANAGEMENT ENDPOINTS
    // =====================================================

    if ($action === 'get_users' && $method === 'GET') {
        if (!checkPermission('admin')) {
            http_response_code(403);
            jsonResponse(['error' => 'Admin access required']);
        }
        
        $stmt = $pdo->query("SELECT id, username, full_name, email, role, status, last_login, created_at FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(['users' => $users]);
    }

    if ($action === 'create_user' && $method === 'POST') {
        if (!checkPermission('admin')) {
            http_response_code(403);
            jsonResponse(['error' => 'Admin access required']);
        }
        
        $data = getJsonInput();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        $full_name = $data['full_name'] ?? '';
        $email = $data['email'] ?? '';
        $role = $data['role'] ?? 'manager';
        $status = $data['status'] ?? 'active';
        
        if (!$username || !$password || !$full_name) {
            jsonResponse(['status' => 'error', 'message' => 'Username, password, and full name are required']);
        }
        
        if (!in_array($role, ['admin', 'manager', 'viewer'])) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid role']);
        }
        
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            jsonResponse(['status' => 'error', 'message' => 'Username already exists']);
        }
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $created_by = getCurrentUserId();
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $hashed_password, $full_name, $email, $role, $status, $created_by]);
        
        jsonResponse(['status' => 'success', 'user_id' => $pdo->lastInsertId()]);
    }

    if ($action === 'update_user' && $method === 'POST') {
        if (!checkPermission('admin')) {
            http_response_code(403);
            jsonResponse(['error' => 'Admin access required']);
        }
        
        $data = getJsonInput();
        $id = $_GET['id'] ?? 0;
        $full_name = $data['full_name'] ?? '';
        $email = $data['email'] ?? '';
        $role = $data['role'] ?? '';
        $status = $data['status'] ?? '';
        
        if (!$id || !$full_name) {
            jsonResponse(['status' => 'error', 'message' => 'User ID and full name are required']);
        }
        
        $updates = [];
        $params = [];
        
        if ($full_name) {
            $updates[] = "full_name = ?";
            $params[] = $full_name;
        }
        if ($email !== null) {
            $updates[] = "email = ?";
            $params[] = $email;
        }
        if ($role && in_array($role, ['admin', 'manager', 'viewer'])) {
            $updates[] = "role = ?";
            $params[] = $role;
        }
        if ($status && in_array($status, ['active', 'inactive'])) {
            $updates[] = "status = ?";
            $params[] = $status;
        }
        
        $params[] = $id;
        
        $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        jsonResponse(['status' => 'success']);
    }

    if ($action === 'change_password' && $method === 'POST') {
        $data = getJsonInput();
        $user_id = $_GET['id'] ?? null;
        $new_password = $data['password'] ?? '';
        
        // Admins can change any user's password, users can change their own
        if (!checkPermission('admin') && $user_id != getCurrentUserId()) {
            http_response_code(403);
            jsonResponse(['error' => 'Permission denied']);
        }
        
        if (!$new_password || strlen($new_password) < 6) {
            jsonResponse(['status' => 'error', 'message' => 'Password must be at least 6 characters']);
        }
        
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id ?: getCurrentUserId()]);
        
        jsonResponse(['status' => 'success']);
    }

    if ($action === 'delete_user' && $method === 'POST') {
        if (!checkPermission('admin')) {
            http_response_code(403);
            jsonResponse(['error' => 'Admin access required']);
        }
        
        $id = $_GET['id'] ?? 0;
        
        if ($id == getCurrentUserId()) {
            jsonResponse(['status' => 'error', 'message' => 'Cannot delete your own account']);
        }
        
        // Check if this is the last admin
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND status = 'active'");
        $result = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if ($user['role'] === 'admin' && $result['count'] <= 1) {
            jsonResponse(['status' => 'error', 'message' => 'Cannot delete the last admin user']);
        }
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonResponse(['status' => 'success']);
    }

    if ($action === 'get_current_user' && $method === 'GET') {
        jsonResponse([
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null,
            'role' => $_SESSION['role'] ?? null
        ]);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>