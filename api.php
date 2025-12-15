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

    if ($action === 'get_public_transfer' && $method === 'GET') {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            jsonResponse(['error' => 'Invalid ID parameter']);
        }
        
        // Fetch status and review data
        $stmt = $pdo->prepare("SELECT id, name, plate, status, serviceDate as serviceDate, user_response as userResponse, review_stars as reviewStars, review_comment as reviewComment FROM transfers WHERE id = ?");
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

    // --- ADD TRANSFER ENDPOINT ---
    if ($action === 'add_transfer' && $method === 'POST') {
        $data = getJsonInput();

        if (empty($data) || !isset($data['plate']) || !isset($data['name']) || !isset($data['amount'])) {
            http_response_code(400);
            jsonResponse(['status' => 'error', 'message' => 'Missing required fields: plate, name, amount']);
        }

        try {
            // Clean and validate amount
            $amount = str_replace([',', ' '], ['', ''], $data['amount']);
            if (!is_numeric($amount)) {
                jsonResponse(['status' => 'error', 'message' => 'Invalid amount format']);
            }
            
            $stmt = $pdo->prepare("INSERT INTO transfers (plate, name, amount, franchise, rawText, status, created_at) 
                                   VALUES (?, ?, ?, ?, ?, 'New', NOW())");
            
            $stmt->execute([
                trim($data['plate']),
                trim($data['name']), 
                $amount,
                isset($data['franchise']) ? trim($data['franchise']) : '',
                isset($data['rawText']) ? trim($data['rawText']) : ''
            ]);

            $newId = $pdo->lastInsertId();
            
            jsonResponse(['status' => 'success', 'id' => $newId, 'message' => 'Transfer added successfully']);
        } catch (Exception $e) {
            error_log("Add transfer error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Failed to add transfer: ' . $e->getMessage()]);
        }
    }

    if ($action === 'update_transfer' && $method === 'POST') {
        $data = $_POST;
        $id = $data['id'] ?? null;

        if (!$id || empty($data)) {
            http_response_code(400);
            jsonResponse(['error' => 'Invalid ID or data']);
            return;
        }

        try {
            $field_map = [
                'name' => 'name',
                'plate' => 'plate',
                'amount' => 'amount',
                'status' => 'status',
                'phone' => 'phone',
                'serviceDate' => 'service_date',
                'franchise' => 'franchise',
                'internalNotes' => 'internalNotes',
                'systemLogs' => 'systemLogs',
                'user_response' => 'user_response',
                'reviewStars' => 'review_stars',
                'reviewComment' => 'review_comment'
            ];

            $update_fields = [];
            $params = [];

            foreach ($data as $key => $value) {
                if (array_key_exists($key, $field_map)) {
                    $db_field = $field_map[$key];
                    $update_fields[] = "`$db_field` = ?";
                    
                    if (is_array($value)) {
                        $params[] = json_encode($value);
                    } else {
                        $params[] = ($value === '') ? null : $value;
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

    // --- ACCEPT RESCHEDULE REQUEST ---
    if ($action === 'accept_reschedule' && $method === 'POST') {
        $data = getJsonInput();
        $id = intval($_GET['id'] ?? 0);
        $serviceDate = $data['service_date'] ?? null;

        if ($id > 0 && $serviceDate) {
            // Update service date and clear reschedule request, mark as confirmed
            $pdo->prepare("UPDATE transfers SET serviceDate = ?, user_response = 'Confirmed', reschedule_date = NULL, reschedule_comment = NULL WHERE id = ?")
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
                $stmt = $pdo->prepare("SELECT content FROM sms_templates WHERE slug = 'reschedule_accepted' AND is_active = 1");
                $stmt->execute();
                $template = $stmt->fetchColumn();
                
                if (!$template) {
                    // No template found in database - skip SMS
                    error_log("No active reschedule_accepted template found in database, skipping SMS");
                } else {
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
        $stmt = $pdo->prepare("SELECT *, service_date as serviceDate, user_response as user_response, review_stars as reviewStars, review_comment as reviewComment, reschedule_date as rescheduleDate, reschedule_comment as rescheduleComment FROM transfers WHERE status IN ('New', 'Processing', 'Called', 'Parts Ordered', 'Parts Arrived', 'Scheduled', 'Completed') ORDER BY created_at DESC");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['internalNotes'] = json_decode($row['internalNotes'] ?? '[]');
            $row['systemLogs'] = json_decode($row['systemLogs'] ?? '[]');
            // serviceDate is already correctly named in the database
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
        $stmt = $pdo->prepare("SELECT slug, content, workflow_stages, is_active FROM sms_templates WHERE is_active = 1 ORDER BY slug");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convert workflow_stages JSON to array
        foreach ($rows as &$row) {
            $row['workflow_stages'] = json_decode($row['workflow_stages'] ?? '[]', true);
        }

        jsonResponse($rows ?: []);
    }

    if ($action === 'get_workflow_stages' && $method === 'GET') {
        $stmt = $pdo->prepare("SELECT stage_name, description, stage_order FROM workflow_stages WHERE is_active = 1 ORDER BY stage_order");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse($rows ?: []);
    }

    if ($action === 'save_templates' && $method === 'POST') {
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['error' => 'Manager or Admin access required to manage SMS templates']);
        }

        $data = getJsonInput();

        // Validate input data
        if (empty($data) || !is_array($data)) {
            http_response_code(400);
            jsonResponse(['error' => 'Invalid or empty data received']);
            exit;
        }

        try {
            // Check if table exists first
            $tableExists = $pdo->query("SHOW TABLES LIKE 'sms_templates'")->rowCount() > 0;
            
            if (!$tableExists) {
                error_log("save_templates: sms_templates table does not exist");
                http_response_code(500);
                jsonResponse(['error' => 'SMS templates table does not exist. Please run database setup scripts.']);
                exit;
            }

            // Ensure table has new columns (defensive migration) - outside transaction
            try {
                $pdo->exec("ALTER TABLE sms_templates
                           ADD COLUMN IF NOT EXISTS workflow_stages JSON DEFAULT NULL,
                           ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT 1,
                           ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                           ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            } catch (Exception $alterError) {
                // Continue anyway - columns might already exist
            }

            // Check if we can query the table
            try {
                $testStmt = $pdo->query("SELECT COUNT(*) as count FROM sms_templates");
                $testResult = $testStmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $testError) {
                http_response_code(500);
                jsonResponse(['error' => 'Cannot access SMS templates table: ' . $testError->getMessage()]);
                exit;
            }

            $pdo->beginTransaction();

            foreach ($data as $slug => $templateData) {
                $content = $templateData['content'] ?? '';
                $workflowStages = $templateData['workflow_stages'] ?? [];
                $isActive = $templateData['is_active'] ?? true;

                // Validate data types
                if (!is_array($workflowStages)) {
                    $workflowStages = [];
                }
                if (!is_bool($isActive)) {
                    $isActive = (bool)$isActive;
                }

                // Validate slug and content
                if (empty($slug) || !is_string($slug)) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    http_response_code(400);
                    jsonResponse(['error' => 'Invalid template slug']);
                    exit;
                }

                if (!is_string($content)) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    http_response_code(400);
                    jsonResponse(['error' => 'Invalid template content']);
                    exit;
                }

                // Convert workflow stages to JSON
                $workflowStagesJson = json_encode($workflowStages);

                // Insert or update template
                $stmt = $pdo->prepare("INSERT INTO sms_templates (slug, content, workflow_stages, is_active, updated_at)
                                      VALUES (?, ?, ?, ?, NOW())
                                      ON DUPLICATE KEY UPDATE
                                      content = VALUES(content),
                                      workflow_stages = VALUES(workflow_stages),
                                      is_active = VALUES(is_active),
                                      updated_at = NOW()");

                $stmt->execute([$slug, $content, $workflowStagesJson, $isActive ? 1 : 0]);
            }

            $pdo->commit();
            jsonResponse(['status' => 'success', 'message' => 'Templates saved successfully']);

        } catch (Exception $e) {
            // Only rollback if there's an active transaction
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Database error in save_templates: " . $e->getMessage());
            http_response_code(500);
            jsonResponse(['error' => 'Failed to save templates: ' . $e->getMessage()]);
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
        $description = $data['description'] ?? null;

        if (!$transfer_id || empty($parts_list)) {
            http_response_code(400);
            jsonResponse(['error' => 'Transfer ID and parts list are required']);
        }

        // Calculate total cost
        $total_cost = 0;
        foreach ($parts_list as $part) {
            $total_cost += ($part['quantity'] ?? 0) * ($part['price'] ?? 0);
        }

        $stmt = $pdo->prepare("INSERT INTO parts_collections (transfer_id, parts_list, total_cost, assigned_manager_id, currency, description) VALUES (?, ?, ?, ?, 'GEL', ?)");
        $stmt->execute([$transfer_id, json_encode($parts_list), $total_cost, $assigned_manager_id, $description]);
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
        $description = $data['description'] ?? null;

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

        if ($description !== null) {
            $query .= ", description = ?";
            $params[] = $description;
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

    // --- DELETE TRANSFER ENDPOINT ---
    if ($action === 'delete_transfer' && $method === 'POST') {
        // Check permissions - managers and admins can delete transfers
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['status' => 'error', 'message' => 'Manager access required to delete transfers']);
        }

        $id = $_GET['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            jsonResponse(['status' => 'error', 'message' => 'Transfer ID is required']);
        }

        try {
            // Check if transfer exists
            $stmt = $pdo->prepare("SELECT id FROM transfers WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() === 0) {
                jsonResponse(['status' => 'error', 'message' => 'Transfer not found']);
            }

            // Delete the transfer (CASCADE will handle related parts_collections)
            $stmt = $pdo->prepare("DELETE FROM transfers WHERE id = ?");
            $stmt->execute([$id]);

            jsonResponse(['status' => 'deleted', 'message' => 'Transfer deleted successfully']);
        } catch (Exception $e) {
            error_log("Delete transfer error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Failed to delete transfer: ' . $e->getMessage()]);
        }
    }

    // --- SYNC VEHICLE ENDPOINT ---
    if ($action === 'sync_vehicle' && $method === 'POST') {
        $data = getJsonInput();

        if (empty($data) || !isset($data['plate'])) {
            http_response_code(400);
            jsonResponse(['status' => 'error', 'message' => 'Plate number is required']);
        }

        try {
            $plate = trim($data['plate']);
            $ownerName = isset($data['ownerName']) ? trim($data['ownerName']) : null;
            $phone = isset($data['phone']) ? trim($data['phone']) : null;
            $model = isset($data['model']) ? trim($data['model']) : null;

            // Check if vehicle exists
            $stmt = $pdo->prepare("SELECT id FROM vehicles WHERE plate = ?");
            $stmt->execute([$plate]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update existing vehicle
                $updateFields = [];
                $params = [];

                if ($ownerName !== null) {
                    $updateFields[] = "ownerName = ?";
                    $params[] = $ownerName;
                }
                if ($phone !== null) {
                    $updateFields[] = "phone = ?";
                    $params[] = $phone;
                }
                if ($model !== null) {
                    $updateFields[] = "model = ?";
                    $params[] = $model;
                }

                if (!empty($updateFields)) {
                    $sql = "UPDATE vehicles SET " . implode(', ', $updateFields) . " WHERE plate = ?";
                    $params[] = $plate;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }
            } else {
                // Insert new vehicle
                $stmt = $pdo->prepare("INSERT INTO vehicles (plate, ownerName, phone, model) VALUES (?, ?, ?, ?)");
                $stmt->execute([$plate, $ownerName, $phone, $model]);
            }

            jsonResponse(['status' => 'success', 'message' => 'Vehicle synced successfully']);
        } catch (Exception $e) {
            error_log("Sync vehicle error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Failed to sync vehicle: ' . $e->getMessage()]);
        }
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

            // Define Georgian keywords and section delimiters (with flexible matching)
            $partsHeader = 'დეტალების ჩამონათვალი';
            $laborHeader = 'მომსახურების ჩამონათვალი';
            $sectionEnd = 'ჯამი (ლარი)';

            // Try to find headers with more flexible matching
            $partsHeaderPos = mb_strpos($text, $partsHeader);
            if ($partsHeaderPos === false) {
                // Try without exact spacing
                $partsHeaderPos = mb_strpos($text, 'დეტალების');
                $logContent .= "FALLBACK: Parts header 'დეტალების' at position: $partsHeaderPos\n";
            }
            
            $laborHeaderPos = mb_strpos($text, $laborHeader);
            if ($laborHeaderPos === false) {
                // Try without exact spacing
                $laborHeaderPos = mb_strpos($text, 'მომსახურების');
                $logContent .= "FALLBACK: Labor header 'მომსახურების' at position: $laborHeaderPos\n";
            }

            // Debug logging
            $logContent .= "LOOKING FOR HEADERS:\n";
            $logContent .= "Parts header: '$partsHeader' at position: $partsHeaderPos\n";
            $logContent .= "Labor header: '$laborHeader' at position: $laborHeaderPos\n";
            $logContent .= "Section end: '$sectionEnd' found " . count($endMarkers) . " times at positions: " . implode(', ', $endMarkers) . "\n\n";

            // --- DATA-DRIVEN PARSING FUNCTIONS ---

            // Parses the Parts section, which has multi-line names and quantity/status/price columns
            function parsePartsSection($textBlock) {
                $lines = explode("\n", $textBlock);
                $items = [];
                $nameBuffer = [];

                // Regex to find a line containing quantity and price (status might be optional)
                $dataLineRegex = '/(\d+)\s+[^\s]*\s*([\d,.]+)$/u';

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
                        $singleLineRegex = '/^(.+?)\s+(\d+)\s+[^\s]*\s*([\d,.]+)$/u';
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

                // Debug logging
                global $logContent;
                $logContent .= "LABOR LINES TO PROCESS:\n";
                foreach ($lines as $i => $line) {
                    $logContent .= "[$i]: '" . trim($line) . "'\n";
                }
                $logContent .= "--- END LABOR LINES ---\n\n";

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
            $laborHeaderPos = mb_strpos($text, $laborHeader);
            
            // Find all occurrences of section end marker
            $endMarkers = [];
            $offset = 0;
            while (($pos = mb_strpos($text, $sectionEnd, $offset)) !== false) {
                $endMarkers[] = $pos;
                $offset = $pos + 1;
            }

            // Extract parts section: from parts header to labor header (or to first end marker if no labor header)
            if ($partsHeaderPos !== false) {
                $partsStart = $partsHeaderPos + mb_strlen($partsHeader);
                $partsEnd = null;
                
                if ($laborHeaderPos !== false && $laborHeaderPos > $partsStart) {
                    // Parts section ends at labor header
                    $partsEnd = $laborHeaderPos;
                } else {
                    // Find the first end marker after parts header
                    foreach ($endMarkers as $endPos) {
                        if ($endPos > $partsStart) {
                            $partsEnd = $endPos;
                            break;
                        }
                    }
                }
                
                if ($partsEnd !== null) {
                    $partsTextBlock = trim(mb_substr($text, $partsStart, $partsEnd - $partsStart));
                }
                
                // Debug logging
                $logContent .= "PARTS SECTION EXTRACTED:\n" . $partsTextBlock . "\n--- END PARTS ---\n\n";
            }

            // Extract labor section: from labor header to next end marker
            if ($laborHeaderPos !== false) {
                $laborStart = $laborHeaderPos + mb_strlen($laborHeader);
                $laborEnd = null;
                
                // Find the first end marker after labor header
                foreach ($endMarkers as $endPos) {
                    if ($endPos > $laborStart) {
                        $laborEnd = $endPos;
                        break;
                    }
                }
                
                if ($laborEnd !== null) {
                    $laborTextBlock = trim(mb_substr($text, $laborStart, $laborEnd - $laborStart));
                }
                
                // Debug logging
                $logContent .= "LABOR SECTION EXTRACTED:\n" . $laborTextBlock . "\n--- END LABOR ---\n\n";
            }

            $partItems = $partsTextBlock ? parsePartsSection($partsTextBlock) : [];
            $laborItems = $laborTextBlock ? parseLaborSection($laborTextBlock) : [];
            
            // Debug logging
            $logContent .= "PARSED PART ITEMS: " . count($partItems) . "\n";
            foreach ($partItems as $item) {
                $logContent .= "- " . $item['name'] . " (qty: " . $item['quantity'] . ", price: " . $item['price'] . ")\n";
            }
            $logContent .= "\nPARSED LABOR ITEMS: " . count($laborItems) . "\n";
            foreach ($laborItems as $item) {
                $logContent .= "- " . $item['name'] . " (price: " . $item['price'] . ")\n";
            }
            $logContent .= "\n";
            
            $items = array_merge($partItems, $laborItems);

            // Filter out Georgian column headers that might be parsed as item names
            $headersToFilter = ['რაოდენობა', 'სტატუსი', 'ფასი(ლარი)', 'ფასი', 'ლარი'];
            $originalCount = count($items);
            $items = array_filter($items, function($item) use ($headersToFilter) {
                $name = trim($item['name']);
                foreach ($headersToFilter as $header) {
                    if (mb_stripos($name, $header) !== false) {
                        return false; // Exclude this item
                    }
                }
                return !empty($name); // Also exclude empty names
            });
            
            // Debug logging
            $logContent .= "FILTERING: Original items: $originalCount, After filtering: " . count($items) . "\n";
            if ($originalCount > count($items)) {
                $logContent .= "FILTERED ITEMS:\n";
                // We can't easily show what was filtered without more complex logic
            }
            $logContent .= "\nFINAL ITEMS: " . count($items) . "\n";
            foreach ($items as $item) {
                $logContent .= "- " . $item['name'] . " (type: " . $item['type'] . ", qty: " . ($item['quantity'] ?? 1) . ", price: " . $item['price'] . ")\n";
            }
            $logContent .= "\n";

            if (empty($items)) {
                 jsonResponse(['success' => false, 'error' => 'Could not automatically detect any items based on the specified format. Please add them manually.']);
            } else {
                 jsonResponse(['success' => true, 'items' => array_values($items)]); // Re-index array
            }

        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Failed to parse PDF: ' . $e->getMessage()]);
        }
    }

    // --- SEND SMS ENDPOINT ---
    if ($action === 'send_sms' && $method === 'POST') {
        $data = $_POST;
        $to = $data['to'] ?? $data['phone'] ?? '';
        $text = $data['text'] ?? '';

        if (empty($to) || empty($text)) {
            jsonResponse(['status' => 'error', 'message' => 'Phone number and message text are required']);
        }

        // Clean phone number - ensure it starts with country code
        $to = preg_replace('/\D/', '', $to);
        if (!preg_match('/^995/', $to)) {
            $to = '995' . $to;
        }

        if (strlen($to) < 11) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid phone number format']);
        }

        try {
            $api_key = defined('SMS_API_KEY') ? SMS_API_KEY : "5c88b0316e44d076d4677a4860959ef71ce049ce704b559355568a362f40ade1";
            $url = "https://api.gosms.ge/api/sendsms?api_key=$api_key&to=$to&from=OTOMOTORS&text=" . urlencode($text);
            
            error_log("SMS sending attempt: to=$to, text=" . substr($text, 0, 50) . "...");
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'OTOMOTORS Portal'
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                $error = error_get_last();
                error_log("SMS sending failed for $to: HTTP request failed - " . ($error['message'] ?? 'Unknown error'));
                jsonResponse(['status' => 'error', 'message' => 'Failed to send SMS - network error']);
            } else {
                error_log("SMS API response for $to: $response");
                
                // gosms.ge API returns XML response, check for success
                if (strpos($response, '<result>1</result>') !== false || 
                    strpos($response, 'success') !== false || 
                    strpos($response, '<status>success</status>') !== false) {
                    jsonResponse(['status' => 'success', 'message' => 'SMS sent successfully']);
                } else {
                    error_log("SMS sending failed for $to: API response indicates failure: $response");
                    jsonResponse(['status' => 'error', 'message' => 'SMS sending failed - API error']);
                }
            }
        } catch (Exception $e) {
            error_log("SMS sending exception for $to: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Failed to send SMS - exception occurred']);
        }
    }

    // Default response if no action matched
    jsonResponse(['error' => 'Unknown action: ' . $action]);

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