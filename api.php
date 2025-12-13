<?php
require_once 'session_config.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// --- CONFIGURATION ---
require_once 'config.php';

// SERVICE ACCOUNT FILE PATH
$service_account_file = __DIR__ . '/service-account.json';

// --- DB CONNECTION ---
try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    http_response_code(500); 
    die(json_encode(['error' => 'DB Connection failed: ' . $e->getMessage()]));
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') exit(0);

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check authentication for protected endpoints
$publicEndpoints = ['login', 'get_order_status', 'submit_review', 'get_public_transfer', 'user_respond'];
if (!in_array($action, $publicEndpoints) && empty($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// CSRF protection for state-changing operations (POST/DELETE)
// Temporarily disabled for smooth transition - will be re-enabled after testing
// Only validate CSRF for authenticated, non-public endpoints
/*
if ($method === 'POST' && !in_array($action, $publicEndpoints) && !empty($_SESSION['user_id'])) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? getallheaders()['X-CSRF-Token'] ?? '';
    
    if (empty($csrfToken) || $csrfToken !== $_SESSION['csrf_token']) {
        error_log("CSRF token mismatch for action: $action, user: " . ($_SESSION['user_id'] ?? 'unknown'));
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token', 'debug' => 'Token validation failed']));
    }
}
*/

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
    $stmt = $pdo->prepare("SELECT token FROM manager_tokens WHERE token IS NOT NULL AND token != ''");
    $stmt->execute();
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
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            jsonResponse(['error' => 'Invalid ID parameter']);
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
        $id = intval($data['id'] ?? 0);
        $response = $data['response'] ?? 'Confirmed';
        $rescheduleDate = $data['reschedule_date'] ?? null;
        $rescheduleComment = $data['reschedule_comment'] ?? null;
        
        // Validate response value
        $validResponses = ['Confirmed', 'Reschedule Requested', 'Pending', 'Declined'];
        if (!in_array($response, $validResponses)) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid response value']);
        }
        
        if($id > 0) {
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

    if ($action === 'update_transfer' && $method === 'POST') {
        $id = $_GET['id'] ?? null;
        $data = getJsonInput();

        if (!$id || empty($data)) {
            http_response_code(400);
            jsonResponse(['error' => 'Invalid ID or data']);
            return;
        }

        try {
            $allowed_fields = ['status', 'phone', 'serviceDate', 'franchise', 'internalNotes', 'systemLogs', 'user_response'];
            $update_fields = [];
            $params = [];

            foreach ($data as $key => $value) {
                if (in_array($key, $allowed_fields)) {
                    $update_fields[] = "`$key` = ?"; // Use backticks for safety
                    // Handle JSON encoding for array fields
                    if (is_array($value)) {
                        $params[] = json_encode($value);
                    } else {
                        $params[] = ($value === '') ? null : $value; // Allow setting fields to null
                    }
                }
            }

            if (!empty($update_fields)) {
                $sql = "UPDATE transfers SET " . implode(', ', $update_fields) . " WHERE id = ?";
                $params[] = $id;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            jsonResponse(['status' => 'success']);
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Update transfer error for ID $id: " . $e->getMessage());
            jsonResponse(['error' => 'Database error during update.']);
        }
    }

    // --- NEW: SUBMIT REVIEW ---
    if ($action === 'submit_review' && $method === 'POST') {
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        $stars = intval($data['stars'] ?? 5);
        $comment = trim($data['comment'] ?? '');

        // Validate star rating
        if ($stars < 1 || $stars > 5) {
            jsonResponse(['status' => 'error', 'message' => 'Rating must be between 1 and 5']);
        }

        // Sanitize comment (max 1000 chars)
        if (strlen($comment) > 1000) {
            $comment = substr($comment, 0, 1000);
        }

        if($id > 0) {
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
        $id = intval($_GET['id'] ?? 0);
        $serviceDate = $data['service_date'] ?? null;

        if ($id > 0 && $serviceDate) {
            // Update service date and clear reschedule request, mark as confirmed
            $pdo->prepare("UPDATE transfers SET service_date = ?, user_response = 'Confirmed', reschedule_date = NULL, reschedule_comment = NULL WHERE id = ?")
                ->execute([$serviceDate, $id]);

            // Get transfer details for SMS
            $stmt = $pdo->prepare("SELECT name, plate, phone, amount FROM transfers WHERE id = ?");
            $stmt->execute([$id]);
            $tr = $stmt->fetch();

            if ($tr && $tr['phone']) {
                // Send confirmation SMS using template
                $formattedDate = date('M d, Y H:i', strtotime($serviceDate));
                $templateData = [
                    'name' => $tr['name'],
                    'plate' => $tr['plate'],
                    'amount' => $tr['amount'],
                    'date' => $formattedDate
                ];
                
                // Get SMS template
                $stmt = $pdo->prepare("SELECT content FROM sms_templates WHERE slug = 'reschedule_accepted'");
                $stmt->execute();
                $template = $stmt->fetchColumn();
                
                if (!$template) {
                    // Fallback to default template
                    $template = 'გამარჯობა {name}, თქვენი თარიღის შეცვლის მოთხოვნა მიღებულია. ახალი თარიღი: {date}';
                }
                
                // Replace placeholders
                $smsText = str_replace(
                    ['{name}', '{plate}', '{amount}', '{date}'],
                    [$templateData['name'], $templateData['plate'], $templateData['amount'], $templateData['date']],
                    $template
                );
                
                $api_key = defined('SMS_API_KEY') ? SMS_API_KEY : "5c88b0316e44d076d4677a4860959ef71ce049ce704b559355568a362f40ade1";
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
        $id = intval($_GET['id'] ?? 0);

        if ($id > 0) {
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
        // Includes review columns and reschedule data, now includes completed transfers for processing queue
        $stmt = $pdo->prepare("SELECT *, user_response as user_response, review_stars as reviewStars, review_comment as reviewComment, reschedule_date as rescheduleDate, reschedule_comment as rescheduleComment FROM transfers WHERE status IN ('New', 'Processing', 'Called', 'Parts Ordered', 'Parts Arrived', 'Scheduled', 'Completed') ORDER BY created_at DESC");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['internalNotes'] = json_decode($row['internal_notes'] ?? '[]');
            $row['systemLogs'] = json_decode($row['system_logs'] ?? '[]');
            $row['serviceDate'] = $row['service_date'] ?? null;
        }        // Also get vehicles for vehicle DB page
        $vehicleStmt = $pdo->prepare("SELECT * FROM vehicles ORDER BY plate ASC");
        $vehicleStmt->execute();
        $vehicles = $vehicleStmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse([
            'transfers' => $rows,
            'vehicles' => $vehicles
        ]);
    }

    // --- GET TRANSFERS FOR PARTS COLLECTION (exclude Completed) ---
    if ($action === 'get_transfers_for_parts' && $method === 'GET') {
        $stmt = $pdo->prepare("SELECT id, plate, name, status FROM transfers WHERE status != 'Completed' ORDER BY created_at DESC");
        $stmt->execute();
        $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(['transfers' => $transfers]);
    }

    // --- SMS TEMPLATES ACTIONS ---
    if ($action === 'get_sms_templates' && $method === 'GET') {
        $stmt = $pdo->prepare("SELECT slug, content FROM sms_templates");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        jsonResponse($rows ?: new stdClass());
    }
    if ($action === 'save_templates' && $method === 'POST') {
        $data = getJsonInput();

        // Debug: log incoming payload to help diagnose 500 errors
        error_log('save_templates payload: ' . json_encode($data));

        // Validate input data
        if (empty($data) || !is_array($data)) {
            http_response_code(400);
            jsonResponse(['error' => 'Invalid or empty data received']);
            exit;
        }

        try {
            // Ensure table exists (defensive migration) to avoid missing-table 500 errors
            $createSql = "CREATE TABLE IF NOT EXISTS sms_templates (
                slug VARCHAR(50) PRIMARY KEY,
                content TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $pdo->exec($createSql);

            // Use distinct parameter names for the UPDATE clause to avoid PDO native-prep bug
            $stmt = $pdo->prepare("INSERT INTO sms_templates (slug, content) VALUES (:slug, :content) ON DUPLICATE KEY UPDATE content = :content_update");
            foreach ($data as $slug => $content) {
                // Log each insert attempt for debugging
                error_log("save_templates inserting slug={$slug} len=" . strlen($content));
                $params = [':slug' => $slug, ':content' => $content, ':content_update' => $content];
                try {
                    $stmt->execute($params);
                } catch (Exception $ex) {
                    // Log detailed context for debugging parameter issues
                    error_log("save_templates execute failed for slug={$slug}: " . $ex->getMessage());
                    error_log("Query: " . $stmt->queryString);
                    error_log("Params: " . var_export($params, true));
                    throw $ex; // rethrow to be caught by outer catch
                }
            }
            jsonResponse(['status' => 'saved']);
        } catch (Exception $e) {
            error_log("Database error in save_templates: " . $e->getMessage());
            http_response_code(500);
            jsonResponse(['message' => 'Database error: ' . $e->getMessage(), 'error' => true]);
        }
    }

    // --- SMS PARSING TEMPLATES ENDPOINTS ---
    if ($action === 'get_parsing_templates' && $method === 'GET') {
        try {
            $stmt = $pdo->prepare("SELECT id, name, insurance_company, template_pattern, field_mappings, is_active FROM sms_parsing_templates WHERE is_active = 1 ORDER BY insurance_company, name");
            $stmt->execute();
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decode JSON field_mappings for each template
            foreach ($templates as &$template) {
                $template['field_mappings'] = json_decode($template['field_mappings'], true);
            }
            
            jsonResponse($templates ?: []);
        } catch (Exception $e) {
            error_log("Database error in get_parsing_templates: " . $e->getMessage());
            http_response_code(500);
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()]);
        }
    }

    // --- CUSTOMER REVIEWS ENDPOINTS ---
    if ($action === 'get_reviews' && $method === 'GET') {
        try {
            $stmt = $pdo->prepare("SELECT * FROM customer_reviews ORDER BY created_at DESC");
            $stmt->execute();
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
        $id = intval($_GET['id'] ?? 0);
        $status = $data['status'] ?? 'pending';
        
        if ($id > 0 && in_array($status, ['pending', 'approved', 'rejected'])) {
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
        
        $stmt = $pdo->prepare("SELECT id, username, full_name, email, role, status, last_login, created_at FROM users ORDER BY created_at DESC");
        $stmt->execute();
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
        $id = intval($_GET['id'] ?? 0);
        $full_name = $data['full_name'] ?? '';
        $email = $data['email'] ?? '';
        $role = $data['role'] ?? '';
        $status = $data['status'] ?? '';
        
        if ($id <= 0 || !$full_name) {
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
        $user_id = intval($_GET['id'] ?? 0);
        $new_password = $data['password'] ?? '';
        
        // Use current user if no ID provided
        if ($user_id <= 0) {
            $user_id = getCurrentUserId();
        }
        
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
        $stmt->execute([$hashed_password, $user_id]);
        
        jsonResponse(['status' => 'success']);
    }

    if ($action === 'delete_user' && $method === 'POST') {
        if (!checkPermission('admin')) {
            http_response_code(403);
            jsonResponse(['error' => 'Admin access required']);
        }
        
        $id = intval($_GET['id'] ?? 0);
        
        if ($id <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid user ID']);
        }
        
        if ($id == getCurrentUserId()) {
            jsonResponse(['status' => 'error', 'message' => 'Cannot delete your own account']);
        }
        
        // Check if this is the last admin
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND status = 'active'");
        $stmt->execute();
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

    // Translation Management Endpoints
    if ($action === 'save_translation' && $method === 'POST') {
        if (!checkPermission('admin')) {
            http_response_code(403);
            jsonResponse(['error' => 'Admin access required']);
        }

        $data = getJsonInput();
        $key = trim($data['key'] ?? '');
        $text = trim($data['text'] ?? '');
        $lang = trim($data['lang'] ?? '');

        if (!$key || !$text || !$lang) {
            jsonResponse(['success' => false, 'message' => 'Missing required fields']);
        }

        require_once 'language.php';
        $success = save_translation($key, $text, $lang);

        jsonResponse(['success' => $success]);
    }

    if ($action === 'export_translations' && $method === 'GET') {
        if (!checkPermission('admin')) {
            http_response_code(403);
            jsonResponse(['error' => 'Admin access required']);
        }

        $lang = $_GET['lang'] ?? get_current_language();

        require_once 'language.php';
        $translations = get_all_translations($lang);

        jsonResponse([
            'success' => true,
            'translations' => $translations,
            'language' => $lang
        ]);
    }

    if ($action === 'set_language' && $method === 'POST') {
        $data = getJsonInput();
        $lang = trim($data['language'] ?? '');

        require_once 'language.php';
        $success = set_language($lang);

        jsonResponse(['success' => $success]);
    }

    if ($action === 'get_current_user' && $method === 'GET') {
        jsonResponse([
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null,
            'role' => $_SESSION['role'] ?? null
        ]);
    }

    // --------------------------------------------------
    // PARTS COLLECTIONS ENDPOINTS
    // --------------------------------------------------
    if ($action === 'get_parts_collections' && $method === 'GET') {
        $transfer_id = $_GET['transfer_id'] ?? null;
        $status = $_GET['status'] ?? null;

        $query = "SELECT pc.*, t.plate AS transfer_plate, t.name AS transfer_name, u.username as assigned_manager_username, u.full_name as manager_full_name 
                  FROM parts_collections pc 
                  JOIN transfers t ON pc.transfer_id = t.id
                  LEFT JOIN users u ON pc.assigned_manager_id = u.id";
        $params = [];

        if ($transfer_id) {
            $query .= " WHERE pc.transfer_id = ?";
            $params[] = $transfer_id;
        }

        if ($status) {
            $query .= ($transfer_id ? " AND" : " WHERE") . " pc.status = ?";
            $params[] = $status;
        }

        $query .= " ORDER BY pc.created_at DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $collections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse(['success' => true, 'collections' => $collections]);
    }

    if ($action === 'create_parts_collection' && $method === 'POST') {
        $data = getJsonInput();
        $transfer_id = $data['transfer_id'] ?? null;
        $parts_list = $data['parts_list'] ?? [];
        $assigned_manager_id = $data['assigned_manager_id'] ?? null;

        if (!$transfer_id || empty($parts_list)) {
            http_response_code(400);
            jsonResponse(['error' => 'Transfer ID and parts list are required']);
        }

        // Calculate total cost
        $total_cost = 0;
        foreach ($parts_list as $part) {
            $total_cost += ($part['quantity'] ?? 0) * ($part['price'] ?? 0);
        }

        $stmt = $pdo->prepare("INSERT INTO parts_collections (transfer_id, parts_list, total_cost, assigned_manager_id, currency) VALUES (?, ?, ?, ?, 'GEL')");
        $stmt->execute([$transfer_id, json_encode($parts_list), $total_cost, $assigned_manager_id]);
        $new_collection_id = $pdo->lastInsertId();

        // --- NEW: UPDATE SUGGESTIONS ---
        update_suggestions_from_list($pdo, $parts_list);

        jsonResponse(['success' => true, 'id' => $new_collection_id]);
    }

    if ($action === 'update_parts_collection' && $method === 'POST') {
        $data = getJsonInput();
        $id = $data['id'] ?? null;
        $parts_list = $data['parts_list'] ?? [];
        $new_status = $data['status'] ?? null;
        $assigned_manager_id = $data['assigned_manager_id'] ?? null;

        if (!$id) {
            http_response_code(400);
            jsonResponse(['error' => 'Collection ID is required']);
        }

        // Get OLD status and transfer_id before updating
        $stmt = $pdo->prepare("SELECT status, transfer_id FROM parts_collections WHERE id = ?");
        $stmt->execute([$id]);
        $collection_info = $stmt->fetch(PDO::FETCH_ASSOC);
        $old_status = $collection_info['status'] ?? null;
        $transfer_id = $collection_info['transfer_id'] ?? null;

        // Calculate total cost
        $total_cost = 0;
        foreach ($parts_list as $part) {
            $total_cost += ($part['quantity'] ?? 0) * ($part['price'] ?? 0);
        }

        $query = "UPDATE parts_collections SET parts_list = ?, total_cost = ?";
        $params = [json_encode($parts_list), $total_cost];

        if ($new_status !== null) {
            $query .= ", status = ?";
            $params[] = $new_status;
        }

        if ($assigned_manager_id !== null) {
            $query .= ", assigned_manager_id = ?";
            $params[] = $assigned_manager_id;
        }

        $query .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        // --- NEW LOGIC: Connect to processing queue ---
        if ($new_status === 'collected' && $old_status !== 'collected' && $transfer_id) {
            // 1. Update transfer status
            $update_transfer_stmt = $pdo->prepare("UPDATE transfers SET status = 'Parts Arrived' WHERE id = ?");
            $update_transfer_stmt->execute([$transfer_id]);

            // 2. Send 'Parts Arrived' SMS
            $stmt = $pdo->prepare("SELECT name, plate, phone, amount FROM transfers WHERE id = ?");
            $stmt->execute([$transfer_id]);
            $tr = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($tr && !empty($tr['phone'])) {
                $stmt = $pdo->prepare("SELECT content FROM sms_templates WHERE slug = 'parts_arrived'");
                $stmt->execute();
                $template = $stmt->fetchColumn();
                
                if ($template) {
                    $link = "https://portal.otoexpress.ge/public_view.php?id=" . $transfer_id;
                    $smsText = str_replace(
                        ['{name}', '{plate}', '{amount}', '{link}'],
                        [$tr['name'], $tr['plate'], $tr['amount'], $link],
                        $template
                    );
                    
                    $api_key = defined('SMS_API_KEY') ? SMS_API_KEY : "5c88b0316e44d076d4677a4860959ef71ce049ce704b559355568a362f40ade1";
                    @file_get_contents("https://api.gosms.ge/api/sendsms?api_key=$api_key&to={$tr['phone']}&from=OTOMOTORS&text=" . urlencode($smsText));
                }
            }
            
            // 3. Add system log
            $log_message = "Parts collection #{$id} marked 'collected'. Case status automatically updated to 'Parts Arrived' and confirmation SMS sent.";
            $log_stmt = $pdo->prepare("UPDATE transfers SET system_logs = JSON_ARRAY_APPEND(COALESCE(system_logs, '[]'), '$', CAST(? AS JSON)) WHERE id = ?");
            $log_stmt->execute([json_encode(['timestamp' => date('Y-m-d H:i:s'), 'message' => $log_message]), $transfer_id]);
        }

        jsonResponse(['success' => true]);
    }

    if ($action === 'delete_parts_collection' && $method === 'POST') {
        $data = getJsonInput();
        $id = $data['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            jsonResponse(['error' => 'Collection ID is required']);
        }

        $stmt = $pdo->prepare("DELETE FROM parts_collections WHERE id = ?");
        $stmt->execute([$id]);

        jsonResponse(['success' => true]);
    }

    // --------------------------------------------------
    // PARTS SUGGESTIONS ENDPOINT
    // --------------------------------------------------
    if ($action === 'get_item_suggestions' && $method === 'GET') {
        $type = $_GET['type'] ?? 'part'; // 'part' or 'labor'
        $stmt = $pdo->prepare("SELECT name FROM item_suggestions WHERE type = ? ORDER BY usage_count DESC, name ASC LIMIT 100");
        $stmt->execute([$type]);
        $suggestions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        jsonResponse(['suggestions' => $suggestions]);
    }

    // --------------------------------------------------
    // GET MANAGERS ENDPOINT
    // --------------------------------------------------
    if ($action === 'get_managers' && $method === 'GET') {
        $stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE role IN ('admin', 'manager') ORDER BY full_name");
        $stmt->execute();
        $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse(['managers' => $managers]);
    }

    // --- NEW: PDF INVOICE PARSING ---
    if ($action === 'parse_invoice_pdf' && $method === 'POST') {
        if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] != UPLOAD_ERR_OK) {
            jsonResponse(['success' => false, 'error' => 'PDF file not uploaded correctly.']);
            exit;
        }

        // Include the Composer autoloader
        $autoloader = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoloader)) {
            jsonResponse(['success' => false, 'error' => 'PDF parsing library not installed. Please run "composer require smalot/pdfparser".']);
            exit;
        }
        require_once $autoloader;

        $filePath = $_FILES['pdf']['tmp_name'];
        $items = [];
        
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
            
            // Log the raw extracted text for debugging
            $logContent = "--- PDF PARSE ATTEMPT: " . date('Y-m-d H:i:s') . " ---\n" . $text . "\n--- END ---\n\n";
            file_put_contents(__DIR__ . '/error_log', $logContent, FILE_APPEND);

            // Define Georgian keywords and section delimiters
            $partsHeader = 'დეტალების ჩამონათვალი';
            $laborHeader = 'მომსახურების ჩამონათვალი';
            $sectionEnd = 'ჯამი (ლარი)';

            // --- DATA-DRIVEN PARSING FUNCTIONS ---

            // Parses the Parts section, which has multi-line names and quantity/status/price columns
            function parsePartsSection($textBlock) {
                $lines = explode("\n", $textBlock);
                $items = [];
                $nameBuffer = [];

                // Regex to find a line containing quantity, status, and price
                $dataLineRegex = '/(\d+)\s+[^\s]+\s+([\d,.]+)$/u';

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    $matches = [];
                    // Check if the current line is a data line (quantity, status, price)
                    if (preg_match($dataLineRegex, $line, $matches)) {
                        $quantity = (int)$matches[1];
                        $price = (float)str_replace(',', '', $matches[2]);
                        
                        // The name is what we've collected in the buffer
                        if (!empty($nameBuffer)) {
                            $name = implode(' ', $nameBuffer);
                            $items[] = ['name' => $name, 'quantity' => $quantity, 'price' => $price, 'type' => 'part'];
                            $nameBuffer = []; // Reset buffer after creating an item
                        }
                    } else {
                        // This line is part of an item's name. It might be a single-line item name.
                        // Check if it's a single line item with name and data together
                        $singleLineRegex = '/^(.+?)\s+(\d+)\s+[^\s]+\s+([\d,.]+)$/u';
                        if (preg_match($singleLineRegex, $line, $matches)) {
                             $name = trim(implode(' ', $nameBuffer) . ' ' . $matches[1]);
                             $quantity = (int)$matches[2];
                             $price = (float)str_replace(',', '', $matches[3]);
                             $items[] = ['name' => $name, 'quantity' => $quantity, 'price' => $price, 'type' => 'part'];
                             $nameBuffer = [];
                        } else {
                            // It's just part of a name, so add it to the buffer.
                            $nameBuffer[] = $line;
                        }
                    }
                }
                return $items;
            }

            // Parses the Labor section, which has multi-line names and just a price column
            function parseLaborSection($textBlock) {
                $lines = explode("\n", $textBlock);
                $items = [];
                $nameBuffer = [];

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    // Regex to find a line that is ONLY a price
                    $priceOnlyRegex = '/^([\d,.]+)$/u';
                    // Regex for a single line item: (Name) (Price)
                    $singleLineRegex = '/^(.+?)\s+([\d,.]+)$/u';

                    $matches = [];
                    // Check if the line is just a price (end of a multi-line item)
                    if (preg_match($priceOnlyRegex, $line, $matches)) {
                        if (!empty($nameBuffer)) {
                            $price = (float)str_replace(',', '', $matches[1]);
                            $name = implode(' ', $nameBuffer);
                            $items[] = ['name' => $name, 'quantity' => 1, 'price' => $price, 'type' => 'labor'];
                            $nameBuffer = []; // Reset
                        }
                    } elseif (preg_match($singleLineRegex, $line, $matches)) {
                        // It's a single line item
                        $name = trim(implode(' ', $nameBuffer) . ' ' . $matches[1]);
                        $price = (float)str_replace(',', '', $matches[2]);
                        $items[] = ['name' => $name, 'quantity' => 1, 'price' => $price, 'type' => 'labor'];
                        $nameBuffer = [];
                    } else {
                        // It's part of a name, add to buffer
                        $nameBuffer[] = $line;
                    }
                }
                return $items;
            }

            // --- MAIN LOGIC ---

            // Isolate the text for each section
            $partsTextBlock = '';
            $laborTextBlock = '';

            $partsHeaderPos = mb_strpos($text, $partsHeader);
            if ($partsHeaderPos !== false) {
                $partsStart = $partsHeaderPos + mb_strlen($partsHeader);
                $partsEnd = mb_strpos($text, $sectionEnd, $partsStart);
                if ($partsEnd !== false) {
                    $partsTextBlock = trim(mb_substr($text, $partsStart, $partsEnd - $partsStart));
                }
            }

            $laborHeaderPos = mb_strpos($text, $laborHeader);
            if ($laborHeaderPos !== false) {
                $laborStart = $laborHeaderPos + mb_strlen($laborHeader);
                $laborEnd = mb_strpos($text, $sectionEnd, $laborStart);
                if ($laborEnd !== false) {
                    $laborTextBlock = trim(mb_substr($text, $laborStart, $laborEnd - $laborStart));
                }
            }

            $partItems = $partsTextBlock ? parsePartsSection($partsTextBlock) : [];
            $laborItems = $laborTextBlock ? parseLaborSection($laborTextBlock) : [];
            
            $items = array_merge($partItems, $laborItems);

            if (empty($items)) {
                 jsonResponse(['success' => false, 'error' => 'Could not automatically detect any items based on the specified format. Please add them manually.']);
            } else {
                 jsonResponse(['success' => true, 'items' => $items]);
            }

        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Failed to parse PDF: ' . $e->getMessage()]);
        }
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

// --- HELPER FUNCTION FOR SUGGESTIONS ---
function update_suggestions_from_list($pdo, $items) {
    if (empty($items)) {
        return;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO item_suggestions (name, type, usage_count) 
        VALUES (:name, :type, 1) 
        ON DUPLICATE KEY UPDATE usage_count = usage_count + 1
    ");

    foreach ($items as $item) {
        if (!empty($item['name']) && !empty($item['type'])) {
            $stmt->execute([
                ':name' => trim($item['name']),
                ':type' => $item['type']
            ]);
        }
    }
}
?>