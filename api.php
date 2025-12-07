<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't output errors directly
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);

// Set custom error handler to catch all errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    header("Content-Type: application/json");
    http_response_code(500);
    echo json_encode([
        'error' => 'PHP Error',
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline
    ]);
    exit;
});

// Set exception handler
set_exception_handler(function($e) {
    header("Content-Type: application/json");
    http_response_code(500);
    echo json_encode([
        'error' => 'PHP Exception',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit;
});

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
    // Read input with size limit (1MB max)
    $rawInput = file_get_contents('php://input', false, null, 0, 1048576);
    
    if ($rawInput === false || strlen($rawInput) === 0) {
        return [];
    }
    
    // Validate UTF-8 encoding
    if (!mb_check_encoding($rawInput, 'UTF-8')) {
        error_log('Invalid UTF-8 in JSON input');
        http_response_code(400);
        die(json_encode(['error' => 'Invalid character encoding']));
    }
    
    // Decode with depth limit (10 levels max)
    $input = json_decode($rawInput, true, 10, JSON_THROW_ON_ERROR);
    
    // Validate no excessively large arrays
    if (is_array($input)) {
        array_walk_recursive($input, function($value, $key) {
            if (is_string($value) && strlen($value) > 100000) {
                throw new Exception('String value too large');
            }
        });
    }
    
    return (json_last_error() === JSON_ERROR_NONE) ? $input : [];
}

/**
 * Secure SMS sending function using cURL with proper validation
 * @param string $to Phone number (validated)
 * @param string $text Message text
 * @param string $api_key API key
 * @return array Response with status and optional error
 */
function sendSecureSMS($to, $text, $api_key) {
    // Validate phone number format (Georgian format: +995XXXXXXXXX or 5XXXXXXXX)
    $to = preg_replace('/[^0-9+]/', '', $to);
    if (empty($to) || (strlen($to) < 9)) {
        return ['success' => false, 'error' => 'Invalid phone number format'];
    }
    
    // Validate API key format (hex string)
    if (!preg_match('/^[a-f0-9]{64}$/i', $api_key)) {
        return ['success' => false, 'error' => 'Invalid API key format'];
    }
    
    // Validate text length (SMS limits)
    if (empty($text) || strlen($text) > 1600) {
        return ['success' => false, 'error' => 'Message text invalid or too long'];
    }
    
    // Build URL with proper encoding
    $params = http_build_query([
        'api_key' => $api_key,
        'to' => $to,
        'from' => 'OTOMOTORS',
        'text' => $text
    ]);
    
    $url = 'https://api.gosms.ge/api/sendsms?' . $params;
    
    // Use cURL for secure HTTP request
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false, // Prevent redirects
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, // Only allow HTTPS
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) {
        error_log("SMS sending failed: $error");
        return ['success' => false, 'error' => 'Network error'];
    }
    
    if ($httpCode !== 200) {
        error_log("SMS API returned HTTP $httpCode: $response");
        return ['success' => false, 'error' => 'API error', 'http_code' => $httpCode];
    }
    
    return ['success' => true, 'response' => $response];
}

// --- HELPER: GET ACCESS TOKEN (V1) ---
function getAccessToken($keyFile) {
    if (!file_exists($keyFile)) return null;
    
    // Validate file size before reading (max 10KB for service account key)
    $fileSize = filesize($keyFile);
    if ($fileSize === false || $fileSize > 10240) {
        error_log("Service account file too large or unreadable: $keyFile");
        return null;
    }
    
    $keyContent = file_get_contents($keyFile);
    if ($keyContent === false) {
        error_log("Failed to read service account file: $keyFile");
        return null;
    }
    
    try {
        $keyData = json_decode($keyContent, true, 5, JSON_THROW_ON_ERROR);
    } catch (Exception $e) {
        error_log("Invalid JSON in service account file: " . $e->getMessage());
        return null;
    }
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
    
    // Validate response size and parse safely
    if (strlen($response) > 10240) {
        error_log("OAuth response too large");
        return null;
    }
    
    try {
        $tokenData = json_decode($response, true, 5, JSON_THROW_ON_ERROR);
    } catch (Exception $e) {
        error_log("Failed to parse OAuth response: " . $e->getMessage());
        return null;
    }
    
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
        
        // Validate FCM response size and parse safely
        if ($res && strlen($res) < 10240) {
            try {
                $results[] = json_decode($res, true, 5, JSON_THROW_ON_ERROR);
            } catch (Exception $e) {
                error_log("Failed to parse FCM response: " . $e->getMessage());
                $results[] = ['error' => 'Invalid response'];
            }
        } else {
            error_log("FCM response too large or empty");
            $results[] = ['error' => 'Invalid response size'];
        }
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
            // Use transaction with row-level locking to prevent race conditions
            try {
                $pdo->beginTransaction();
                
                // Lock the row for update to prevent concurrent modifications
                $stmt = $pdo->prepare("SELECT user_response, name, plate FROM transfers WHERE id = ? FOR UPDATE");
                $stmt->execute([$id]);
                $transfer = $stmt->fetch();
                
                if (!$transfer) {
                    $pdo->rollBack();
                    http_response_code(404);
                    jsonResponse(['status' => 'error', 'message' => 'Transfer not found']);
                }
                
                // Prevent duplicate responses (idempotency check)
                if ($transfer['user_response'] === $response && $response !== 'Reschedule Requested') {
                    $pdo->rollBack();
                    jsonResponse(['status' => 'success', 'message' => 'Response already recorded']);
                }
                
                // Atomic update of user response
                if ($response === 'Reschedule Requested' && $rescheduleDate) {
                    $pdo->prepare("UPDATE transfers SET user_response = ?, reschedule_date = ?, reschedule_comment = ? WHERE id = ?")
                        ->execute([$response, $rescheduleDate, $rescheduleComment, $id]);
                } else {
                    $pdo->prepare("UPDATE transfers SET user_response = ? WHERE id = ?")->execute([$response, $id]);
                }
                
                $pdo->commit();
                
                // Send notification after commit (outside transaction)
                $notificationBody = "{$transfer['name']} ({$transfer['plate']}) marked as: $response";
                if ($rescheduleDate) {
                    $notificationBody .= " - Requested: " . date('M d, Y H:i', strtotime($rescheduleDate));
                }
                sendFCM_V1($pdo, $service_account_file, "Customer Responded", $notificationBody);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Race condition in user_respond: " . $e->getMessage());
                http_response_code(500);
                jsonResponse(['status' => 'error', 'message' => 'Failed to process response']);
            }
            } else {
                jsonResponse(['status' => 'error', 'message' => 'Invalid transfer ID']);
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
                $pdo->beginTransaction();
                
                // Lock row and check if review already exists (prevent duplicates)
                $stmt = $pdo->prepare("SELECT name, plate, review_stars FROM transfers WHERE id = ? FOR UPDATE");
                $stmt->execute([$id]);
                $tr = $stmt->fetch();
                
                if(!$tr) {
                    $pdo->rollBack();
                    http_response_code(404);
                    jsonResponse(['status' => 'error', 'message' => 'Transfer not found']);
                }
                
                // Check if review already submitted (idempotency)
                if ($tr['review_stars'] !== null && $tr['review_stars'] > 0) {
                    $pdo->rollBack();
                    jsonResponse(['status' => 'success', 'message' => 'Review already submitted']);
                }
                
                // Save to customer_reviews table
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                try {
                    $pdo->prepare("INSERT INTO customer_reviews (order_id, customer_name, rating, comment, status, ip_address) VALUES (?, ?, ?, ?, 'pending', ?)")
                        ->execute([$id, $tr['name'], $stars, $comment, $ip]);
                } catch (PDOException $e) {
                    // Table might not exist yet, continue anyway
                    error_log("customer_reviews table issue: " . $e->getMessage());
                }
                
                // Update transfers table atomically
                $pdo->prepare("UPDATE transfers SET review_stars = ?, review_comment = ? WHERE id = ?")->execute([$stars, $comment, $id]);
                
                $pdo->commit();
                
                // Send notification after commit
                sendFCM_V1($pdo, $service_account_file, "New Customer Review!", "{$tr['name']} ({$tr['plate']}) rated: $stars Stars");
                
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Race condition in submit_review: " . $e->getMessage());
                http_response_code(500);
                jsonResponse(['status' => 'error', 'message' => 'Failed to submit review']);
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
                // Send confirmation SMS
                $formattedDate = date('M d, Y H:i', strtotime($serviceDate));
                $smsText = "Hello {$tr['name']}, your reschedule request has been approved! New appointment: {$formattedDate}. Ref: {$tr['plate']}. - OTOMOTORS";
                
                $api_key = defined('SMS_API_KEY') ? SMS_API_KEY : "5c88b0316e44d076d4677a4860959ef71ce049ce704b559355568a362f40ade1";
                $smsResult = sendSecureSMS($tr['phone'], $smsText, $api_key);
                
                if (!$smsResult['success']) {
                    error_log("Failed to send reschedule SMS to {$tr['phone']}: {$smsResult['error']}");
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
        // Includes review columns and reschedule data
        $stmt = $pdo->prepare("SELECT *, user_response as user_response, review_stars as reviewStars, review_comment as reviewComment, reschedule_date as rescheduleDate, reschedule_comment as rescheduleComment FROM transfers ORDER BY created_at DESC");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            // Validate JSON size before decoding (max 100KB per field)
            $notesJson = $row['internal_notes'] ?? '[]';
            $logsJson = $row['system_logs'] ?? '[]';
            
            if (strlen($notesJson) > 102400 || strlen($logsJson) > 102400) {
                error_log("Oversized JSON detected for transfer ID: " . $row['id']);
                $row['internalNotes'] = [];
                $row['systemLogs'] = [];
            } else {
                try {
                    $row['internalNotes'] = json_decode($notesJson, true, 10, JSON_THROW_ON_ERROR) ?: [];
                    $row['systemLogs'] = json_decode($logsJson, true, 10, JSON_THROW_ON_ERROR) ?: [];
                } catch (Exception $e) {
                    error_log("JSON decode error for transfer ID " . $row['id'] . ": " . $e->getMessage());
                    $row['internalNotes'] = [];
                    $row['systemLogs'] = [];
                }
            }
            $row['serviceDate'] = $row['service_date'] ?? null; 
        }
        
        // Also get vehicles for vehicle DB page
        $vehicleStmt = $pdo->prepare("SELECT * FROM vehicles ORDER BY plate ASC");
        $vehicleStmt->execute();
        $vehicles = $vehicleStmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse([
            'transfers' => $rows,
            'vehicles' => $vehicles
        ]);
    }

    // ... (Rest of existing actions: add_transfer, update_transfer, delete_transfer, etc. remain unchanged) ...
    // Keeping previous endpoints for brevity, assume they are present here exactly as before.
    
    // TEST SESSION ENDPOINT
    if ($action === 'test_session' && $method === 'GET') {
        jsonResponse([
            'user_id' => $_SESSION['user_id'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null,
            'can_create' => checkPermission('manager')
        ]);
    }
    
    // CREATE NEW TRANSFER (Manual Order Creation)
    if ($action === 'create_transfer' && $method === 'POST') {
        error_log("Create transfer request from user: " . ($_SESSION['user_id'] ?? 'unknown') . ", role: " . ($_SESSION['role'] ?? 'unknown'));
        
        if (!checkPermission('manager')) {
            error_log("Permission denied for create_transfer. User role: " . ($_SESSION['role'] ?? 'none'));
            jsonResponse(['status' => 'error', 'message' => 'Insufficient permissions. Manager or Admin role required.', 'current_role' => $_SESSION['role'] ?? 'none']);
        }
        
        $data = getJsonInput();
        error_log("Create transfer data: " . json_encode($data));
        
        // Required fields validation
        if (empty($data['plate']) || empty($data['name']) || !isset($data['amount'])) {
            error_log("Create transfer validation failed: missing required fields");
            jsonResponse(['status' => 'error', 'message' => 'Missing required fields: plate, name, amount']);
        }
        
        // Prepare data with defaults
        $plate = strtoupper(trim($data['plate']));
        $name = trim($data['name']);
        $phone = trim($data['phone'] ?? '');
        $amount = floatval($data['amount']);
        $franchise = floatval($data['franchise'] ?? 0);
        $status = $data['status'] ?? 'New';
        $internalNotes = $data['internalNotes'] ?? [];
        $systemLogs = $data['systemLogs'] ?? [];
        
        // Additional validation
        if ($amount <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Amount must be greater than 0']);
        }
        if ($franchise < 0) {
            jsonResponse(['status' => 'error', 'message' => 'Franchise cannot be negative']);
        }
        if (!in_array($status, ['New', 'Processing', 'Called', 'Parts Ordered', 'Parts Arrived', 'Scheduled', 'Completed', 'Issue'])) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid status value']);
        }
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO transfers (plate, name, phone, amount, franchise, status, internal_notes, system_logs, created_at)
                VALUES (:plate, :name, :phone, :amount, :franchise, :status, :internal_notes, :system_logs, NOW())
            ");
            
            $stmt->execute([
                ':plate' => $plate,
                ':name' => $name,
                ':phone' => $phone,
                ':amount' => $amount,
                ':franchise' => $franchise,
                ':status' => $status,
                ':internal_notes' => json_encode($internalNotes),
                ':system_logs' => json_encode($systemLogs)
            ]);
            
            $newId = $pdo->lastInsertId();
            
            jsonResponse([
                'status' => 'success',
                'message' => 'Order created successfully',
                'id' => $newId
            ]);
        } catch (PDOException $e) {
            error_log("Create transfer error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
    
    // 4. UPDATE EXISTING TRANSFER
    if ($action === 'update_transfer' && $method === 'POST') {
        // Authorization: Only managers and admins can update transfers
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['status' => 'error', 'message' => 'Insufficient permissions to update transfers']);
        }
        
        $data = getJsonInput();
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid ID']);
        }
        
        try {
            $pdo->beginTransaction();
            
            // Lock the row to prevent concurrent modifications (pessimistic locking)
            $stmt = $pdo->prepare("SELECT id FROM transfers WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                $pdo->rollBack();
                if ($key === 'internalNotes' || $key === 'systemLogs') {
                    $dbColumn = $key === 'internalNotes' ? 'internal_notes' : 'system_logs';
                    
                    // Validate array size before encoding (max 1000 items, max 100KB total)
                    if (!is_array($val)) {
                        continue; // Skip non-array values
                    }
                    
                    if (count($val) > 1000) {
                        error_log("Array too large for $key: " . count($val) . " items");
                        http_response_code(400);
                        throw new Exception("$key array exceeds maximum size (1000 items)");
                    }
                    
                    $encoded = json_encode($val);
                    if (strlen($encoded) > 102400) {
                        error_log("Encoded JSON too large for $key: " . strlen($encoded) . " bytes");
                        http_response_code(400);
                        throw new Exception("$key data exceeds maximum size (100KB)");
                    }
                    
                    $fields[] = "$dbColumn = :$key";
                    $params[":$key"] = $encoded;
                }ds = []; $params = [':id' => $id];
            foreach ($data as $key => $val) {
                if (in_array($key, ['plate', 'name', 'phone', 'amount', 'serviceDate', 'franchise', 'status', 'operatorComment', 'user_response'])) {
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
            
            $pdo->commit();
            jsonResponse(['status' => 'success']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Race condition in update_transfer: " . $e->getMessage());
            http_response_code(500);
            jsonResponse(['status' => 'error', 'message' => 'Failed to update transfer']);
        }
    }
    
    // ... Include get_vehicles, sync_vehicle, etc. from previous version ...
    if ($action === 'add_transfer' && $method === 'POST') {
        // Authorization: Only managers and admins can add transfers
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['status' => 'error', 'message' => 'Insufficient permissions to add transfers']);
        }
        
        $data = getJsonInput();
        $sql = "INSERT INTO transfers (plate, name, amount, status, phone, rawText, internal_notes, system_logs, user_response) VALUES (:plate, :name, :amount, 'New', '', :rawText, '[]', '[]', 'Pending')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':plate' => $data['plate'] ?? '', ':name' => $data['name'] ?? '', ':amount' => $data['amount'] ?? 0, ':rawText' => $data['rawText'] ?? '']);
        jsonResponse(['id' => $pdo->lastInsertId(), 'status' => 'success']);
    }
    if ($action === 'delete_transfer' && $method === 'POST') {
        // Authorization: Only managers and admins can delete transfers
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['status' => 'error', 'message' => 'Insufficient permissions to delete transfers']);
        }
        
        $id = intval($_GET['id'] ?? 0);
        if ($id > 0) {
            // Verify transfer exists before deleting
            $stmt = $pdo->prepare("SELECT id FROM transfers WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                jsonResponse(['status' => 'error', 'message' => 'Transfer not found']);
            }
            
            $pdo->prepare("DELETE FROM transfers WHERE id = ?")->execute([$id]);
            jsonResponse(['status' => 'deleted']);
        } else {
            jsonResponse(['status' => 'error', 'message' => 'Invalid ID']);
        }
    }
    if ($action === 'get_vehicles' && $method === 'GET') {
        $stmt = $pdo->prepare("SELECT * FROM vehicles ORDER BY plate ASC");
        $stmt->execute();
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    if ($action === 'sync_vehicle' && $method === 'POST') {
        $data = getJsonInput();
        $plate = $data['plate'] ?? '';
        if ($plate) {
            try {
                $pdo->beginTransaction();
                
                // Use row-level locking to prevent race conditions
                $stmt = $pdo->prepare("SELECT id, ownerName, phone FROM vehicles WHERE plate = ? FOR UPDATE");
                $stmt->execute([$plate]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    $updates = []; $params = [':id' => $existing['id']];
                    if (!empty($data['phone']) && $data['phone'] !== $existing['phone']) { 
                        $updates[] = "phone = :phone"; 
                        $params[':phone'] = $data['phone']; 
                    }
                    if (!empty($data['ownerName']) && empty($existing['ownerName'])) { 
                        $updates[] = "ownerName = :ownerName"; 
                        $params[':ownerName'] = $data['ownerName']; 
                    }
                    if (!empty($updates)) {
                        $pdo->prepare("UPDATE vehicles SET " . implode(', ', $updates) . " WHERE id = :id")->execute($params);
                    }
                } else {
                    // Use INSERT IGNORE to handle race condition where another request inserts same plate
                    $pdo->prepare("INSERT IGNORE INTO vehicles (plate, ownerName, phone) VALUES (?, ?, ?)")
                        ->execute([$plate, $data['ownerName'] ?? '', $data['phone'] ?? '']);
                }
                
                $pdo->commit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Race condition in sync_vehicle: " . $e->getMessage());
                http_response_code(500);
                jsonResponse(['status' => 'error', 'message' => 'Failed to sync vehicle']);
            }
        }
        jsonResponse(['status' => 'synced']);
    }
    if ($action === 'save_vehicle' && $method === 'POST') {
        $data = getJsonInput();
        
        // Validation
        if (empty($data['plate'])) {
            jsonResponse(['status' => 'error', 'message' => 'Plate number is required']);
        }
        
        $plate = trim($data['plate']);
        $ownerName = trim($data['ownerName'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $model = trim($data['model'] ?? '');
        
        try {
            if (isset($_GET['id']) && $_GET['id']) {
                $id = intval($_GET['id']);
                // Update existing vehicle
                $stmt = $pdo->prepare("UPDATE vehicles SET plate=?, ownerName=?, phone=?, model=? WHERE id=?");
                $stmt->execute([$plate, $ownerName, $phone, $model, $id]);
            } else {
                // Use INSERT ... ON DUPLICATE KEY UPDATE to handle race conditions
                // Assumes 'plate' has a UNIQUE constraint
                $stmt = $pdo->prepare(
                    "INSERT INTO vehicles (plate, ownerName, phone, model) VALUES (?, ?, ?, ?) 
                     ON DUPLICATE KEY UPDATE ownerName=VALUES(ownerName), phone=VALUES(phone), model=VALUES(model)"
                );
                $stmt->execute([$plate, $ownerName, $phone, $model]);
            }
            jsonResponse(['status' => 'saved']);
        } catch (PDOException $e) {
            error_log("Vehicle save error: " . $e->getMessage());
            http_response_code(500);
            jsonResponse(['status' => 'error', 'message' => 'Failed to save vehicle. Plate may already exist.']);
        }
    }
    if ($action === 'delete_vehicle' && $method === 'POST') {
        // Authorization: Only managers and admins can delete vehicles
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['status' => 'error', 'message' => 'Insufficient permissions to delete vehicles']);
        }
        
        $id = intval($_GET['id'] ?? 0);
        if ($id > 0) {
            // Verify vehicle exists before deleting
            $stmt = $pdo->prepare("SELECT id FROM vehicles WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                jsonResponse(['status' => 'error', 'message' => 'Vehicle not found']);
            }
            
            $pdo->prepare("DELETE FROM vehicles WHERE id=?")->execute([$id]);
            jsonResponse(['status' => 'deleted']);
        } else {
            jsonResponse(['status' => 'error', 'message' => 'Invalid vehicle ID']);
        }
    }
    if ($action === 'send_sms' && $method === 'POST') {
        // Authorization: Only managers and admins can send SMS
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['status' => 'error', 'message' => 'Insufficient permissions to send SMS']);
        }
        
        $data = getJsonInput();
        $to = $data['to'] ?? ''; 
        $text = $data['text'] ?? '';
        
        if (empty($to) || empty($text)) {
            jsonResponse(['status' => 'error', 'message' => 'Missing phone number or message text']);
        }
        
        $api_key = defined('SMS_API_KEY') ? SMS_API_KEY : "5c88b0316e44d076d4677a4860959ef71ce049ce704b559355568a362f40ade1";
        $result = sendSecureSMS($to, $text, $api_key);
        
        if ($result['success']) {
            jsonResponse(['status' => 'success', 'response' => $result['response']]);
        } else {
            http_response_code(500);
            jsonResponse(['status' => 'error', 'message' => $result['error']]);
        }
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
        // Authorization: Only managers and admins can send broadcast notifications
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['status' => 'error', 'message' => 'Insufficient permissions to send broadcasts']);
        }
        
        $data = getJsonInput();
        $title = $data['title'] ?? 'New Notification';
        $body = $data['body'] ?? '';
        $result = sendFCM_V1($pdo, $service_account_file, $title, $body);
        jsonResponse(['status' => 'sent', 'fcm_result' => $result]);
    }
    if ($action === 'get_templates' && $method === 'GET') {
        $stmt = $pdo->prepare("SELECT slug, content FROM sms_templates");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        jsonResponse($rows ?: new stdClass());
    }
    if ($action === 'save_templates' && $method === 'POST') {
        // Authorization: Only admins can modify SMS templates
        if (!checkPermission('admin')) {
            http_response_code(403);
            jsonResponse(['status' => 'error', 'message' => 'Admin access required to modify templates']);
        }
        
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
        // Authorization: Only managers and admins can moderate reviews
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['status' => 'error', 'message' => 'Insufficient permissions to moderate reviews']);
        }
        
        $data = getJsonInput();
        $id = intval($_GET['id'] ?? 0);
        $status = $data['status'] ?? 'pending';
        
        if ($id > 0 && in_array($status, ['pending', 'approved', 'rejected'])) {
            try {
                // Verify review exists before updating
                $stmt = $pdo->prepare("SELECT id FROM customer_reviews WHERE id = ?");
                $stmt->execute([$id]);
                if (!$stmt->fetch()) {
                    http_response_code(404);
                    jsonResponse(['status' => 'error', 'message' => 'Review not found']);
                }
                
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
        
        // Verify user exists and prevent self-demotion from admin
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $targetUser = $stmt->fetch();
        
        if (!$targetUser) {
            http_response_code(404);
            jsonResponse(['status' => 'error', 'message' => 'User not found']);
        }
        
        // Prevent admin from demoting themselves
        if ($id == getCurrentUserId() && $role && $role !== 'admin') {
            jsonResponse(['status' => 'error', 'message' => 'Cannot demote your own admin role']);
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
        
        // Use transaction with locking to atomically check and delete
        try {
            $pdo->beginTransaction();
            
            // Lock the user row and get role
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $pdo->rollBack();
                http_response_code(404);
                jsonResponse(['status' => 'error', 'message' => 'User not found']);
            }
            
            // If deleting an admin, lock all admin rows and count them
            if ($user['role'] === 'admin') {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND status = 'active' FOR UPDATE");
                $stmt->execute();
                $result = $stmt->fetch();
                
                if ($result['count'] <= 1) {
                    $pdo->rollBack();
                    jsonResponse(['status' => 'error', 'message' => 'Cannot delete the last admin user']);
                }
            }
            
            // Safe to delete now
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Race condition in delete_user: " . $e->getMessage());
            http_response_code(500);
            jsonResponse(['status' => 'error', 'message' => 'Failed to delete user']);
        }
        
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