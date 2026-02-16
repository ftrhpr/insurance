<?php
require_once 'session_config.php';

header("Content-Type: application/json");

// CORS: restrict to production domain
$allowed_origins = ['https://portal.otoexpress.ge', 'https://www.portal.otoexpress.ge'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
} else {
    // Same-origin requests have no Origin header — allow those
    if (!empty($origin)) {
        header("Access-Control-Allow-Origin: https://portal.otoexpress.ge");
    }
}
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token");

// --- CONFIGURATION ---
require_once 'config.php';

// SERVICE ACCOUNT FILE PATH
$service_account_file = __DIR__ . '/service-account.json';

// --- DB CONNECTION ---
try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    http_response_code(500); 
    error_log('DB Connection failed: ' . $e->getMessage());
    die(json_encode(['error' => 'Database connection failed. Please try again later.']));
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') exit(0);

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper function to get valid workflow stages from existing repair_stage values
function getValidWorkflowStages() {
    static $valid_stages = null;
    if ($valid_stages === null) {
        // Always include all expected stages
        $valid_stages = ['backlog', 'disassembly', 'body_work', 'processing_for_painting', 'preparing_for_painting', 'painting', 'assembling', 'done'];
        
        // Add any additional stages from database
        try {
            global $pdo;
            $stmt = $pdo->query("SELECT DISTINCT repair_stage FROM transfers WHERE repair_stage IS NOT NULL AND repair_stage NOT IN ('backlog', 'disassembly', 'body_work', 'processing_for_painting', 'preparing_for_painting', 'painting', 'assembling', 'done')");
            $extra_stages = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $valid_stages = array_unique(array_merge($valid_stages, $extra_stages));
        } catch (Exception $e) {
            // Ignore database errors, use default stages
        }
    }
    return $valid_stages;
}

// Helper function to get stage progression from existing repair_stage usage patterns
function getStageProgression() {
    static $stage_progression = null;
    if ($stage_progression === null) {
        // Start with default progression
        $stage_progression = [
            'disassembly' => 'body_work',
            'body_work' => 'processing_for_painting',
            'processing_for_painting' => 'preparing_for_painting',
            'preparing_for_painting' => 'painting',
            'painting' => 'assembling',
            'assembling' => 'done'
        ];
        
        // Try to enhance with actual usage patterns from database
        try {
            global $pdo;
            $stmt = $pdo->query("
                SELECT JSON_UNQUOTE(JSON_EXTRACT(log, '$.from')) as from_stage, 
                       JSON_UNQUOTE(JSON_EXTRACT(log, '$.to')) as to_stage,
                       COUNT(*) as count
                FROM transfers t
                CROSS JOIN JSON_TABLE(t.system_logs, '$[*]' COLUMNS (log JSON PATH '$')) logs
                WHERE JSON_EXTRACT(log, '$.type') = 'move'
                GROUP BY from_stage, to_stage
                ORDER BY count DESC
            ");
            $progressions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Override defaults with actual usage patterns if they exist
            foreach ($progressions as $prog) {
                if ($prog['from_stage'] && $prog['to_stage']) {
                    $stage_progression[$prog['from_stage']] = $prog['to_stage'];
                }
            }
        } catch (Exception $e) {
            // Ignore database errors, use default progression
        }
    }
    return $stage_progression;
}

// Check authentication for protected endpoints
$publicEndpoints = ['login', 'get_order_status', 'submit_review', 'get_public_transfer', 'user_respond', 'get_public_offer', 'redeem_offer', 'track_offer_view', 'save_completion_signature'];
if (!in_array($action, $publicEndpoints) && empty($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// CSRF protection for state-changing operations (POST/DELETE)
if ($method === 'POST' && !in_array($action, $publicEndpoints) && !empty($_SESSION['user_id'])) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    // Also check getallheaders() for case-insensitive header names
    if (empty($csrfToken)) {
        $headers = getallheaders();
        foreach ($headers as $k => $v) {
            if (strtolower($k) === 'x-csrf-token') { $csrfToken = $v; break; }
        }
    }
    if (empty($csrfToken) || $csrfToken !== $_SESSION['csrf_token']) {
        error_log("CSRF token mismatch for action: $action, user: " . ($_SESSION['user_id'] ?? 'unknown'));
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token']));
    }
}

// Check role permissions
function checkPermission($required_role) {
    $user_role = $_SESSION['role'] ?? 'viewer';
    $hierarchy = ['viewer' => 1, 'technician' => 2, 'manager' => 3, 'admin' => 4];
    $userLevel = $hierarchy[$user_role] ?? 0;
    $requiredLevel = $hierarchy[$required_role] ?? 99;
    return $userLevel >= $requiredLevel;
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function jsonResponse($data) {
    echo json_encode($data);
    exit;
}

function getJsonInput() {
    // First try to get JSON from php://input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() === JSON_ERROR_NONE && !empty($input)) {
        return $input;
    }
    // Fallback to $_POST for FormData submissions
    if (!empty($_POST)) {
        // Decode any JSON-encoded values in $_POST
        $data = [];
        foreach ($_POST as $key => $value) {
            // Try to decode JSON strings
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                $data[$key] = $decoded;
            } else {
                $data[$key] = $value;
            }
        }
        return $data;
    }
    return [];
}

// --- HELPER: GET ACCESS TOKEN (V1) ---
function getAccessToken($keyFile) {
    return getAccessTokenWithScope($keyFile, 'https://www.googleapis.com/auth/firebase.messaging');
}

// --- HELPER: GET ACCESS TOKEN WITH CUSTOM SCOPE ---
// Returns ['token' => string] on success, or ['error' => string, 'details' => string] on failure
function getAccessTokenWithScope($keyFile, $scope, $returnDetails = false) {
    if (!file_exists($keyFile)) {
        if ($returnDetails) return ['error' => 'Key file not found', 'details' => $keyFile];
        return null;
    }
    
    $keyData = json_decode(file_get_contents($keyFile), true);
    if (!$keyData || empty($keyData['private_key']) || empty($keyData['client_email'])) {
        if ($returnDetails) return ['error' => 'Invalid key file format', 'details' => 'Missing private_key or client_email'];
        return null;
    }
    
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
    $signResult = openssl_sign($signatureInput, $signature, $keyData['private_key'], 'SHA256');
    if (!$signResult) {
        $opensslError = openssl_error_string();
        if ($returnDetails) return ['error' => 'OpenSSL signing failed', 'details' => $opensslError];
        return null;
    }
    
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
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($curlError) {
        if ($returnDetails) return ['error' => 'cURL error', 'details' => $curlError];
        return null;
    }
    
    $tokenData = json_decode($response, true);
    
    if (isset($tokenData['access_token'])) {
        if ($returnDetails) return ['token' => $tokenData['access_token']];
        return $tokenData['access_token'];
    }
    
    // Error case
    $errorMsg = $tokenData['error_description'] ?? $tokenData['error'] ?? $response;
    error_log("OAuth token error (HTTP $httpCode): $errorMsg");
    if ($returnDetails) return ['error' => 'OAuth failed', 'details' => $errorMsg, 'http_code' => $httpCode];
    return null;
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
        // Support both slug (preferred, secure) and legacy integer ID
        $slug = trim($_GET['slug'] ?? '');
        $id = intval($_GET['id'] ?? 0);
        
        if (empty($slug) && $id <= 0) {
            http_response_code(400);
            jsonResponse(['error' => 'Invalid request']);
        }
        
        // Note: Column migrations moved to fix_db_all.php
        
        // Fetch by slug (preferred) or by id (legacy fallback)
        if (!empty($slug)) {
            // Validate slug format (32-char hex)
            if (!preg_match('/^[a-f0-9]{32}$/', $slug)) {
                http_response_code(400);
                jsonResponse(['error' => 'Invalid request']);
            }
            $stmt = $pdo->prepare("SELECT id, name, plate, status, service_date as serviceDate, user_response as userResponse, review_stars as reviewStars, review_comment as reviewComment, slug FROM transfers WHERE slug = ?");
            $stmt->execute([$slug]);
        } else {
            $stmt = $pdo->prepare("SELECT id, name, plate, status, service_date as serviceDate, user_response as userResponse, review_stars as reviewStars, review_comment as reviewComment, slug FROM transfers WHERE id = ?");
            $stmt->execute([$id]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            http_response_code(404);
            jsonResponse(['error' => 'Not found']);
        }
        
        // Record link open (only first time)
        $pdo->prepare("UPDATE transfers SET link_opened_at = COALESCE(link_opened_at, NOW()) WHERE id = ? AND link_opened_at IS NULL")->execute([$row['id']]);
        
        // Don't expose sequential ID to client - use slug
        $row['slug'] = $row['slug'] ?? '';
        unset($row['id']);
        
        jsonResponse($row);
    }

    if ($action === 'user_respond' && $method === 'POST') {
        $data = getJsonInput();
        $slug = trim($data['slug'] ?? '');
        $id = intval($data['id'] ?? 0);
        $response = $data['response'] ?? 'Confirmed';
        $rescheduleDate = $data['reschedule_date'] ?? null;
        $rescheduleComment = $data['reschedule_comment'] ?? null;
        
        // Validate response value
        $validResponses = ['Confirmed', 'Reschedule Requested', 'Pending', 'Declined'];
        if (!in_array($response, $validResponses)) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid response value']);
        }
        
        // Resolve transfer by slug (preferred) or id (legacy)
        $transfer = null;
        if (!empty($slug) && preg_match('/^[a-f0-9]{32}$/', $slug)) {
            $stmt = $pdo->prepare("SELECT id, name, plate FROM transfers WHERE slug = ?");
            $stmt->execute([$slug]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif ($id > 0) {
            $stmt = $pdo->prepare("SELECT id, name, plate FROM transfers WHERE id = ?");
            $stmt->execute([$id]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($transfer) {
            $transferId = $transfer['id'];
            // Update user response
            $pdo->prepare("UPDATE transfers SET user_response = ? WHERE id = ?")->execute([$response, $transferId]);
            
            // If reschedule request, store the desired date and comment
            if ($response === 'Reschedule Requested' && $rescheduleDate) {
                $pdo->prepare("UPDATE transfers SET reschedule_date = ?, reschedule_comment = ? WHERE id = ?")
                    ->execute([$rescheduleDate, $rescheduleComment, $transferId]);
            }
            
            $notificationBody = "{$transfer['name']} ({$transfer['plate']}) marked as: $response";
            if ($rescheduleDate) {
                $notificationBody .= " - Requested: " . date('M d, Y H:i', strtotime($rescheduleDate));
            }
            sendFCM_V1($pdo, $service_account_file, "Customer Responded", $notificationBody);
        }
        jsonResponse(['status' => 'success']);
    }

    // --- NEW: SUBMIT REVIEW ---
    if ($action === 'submit_review' && $method === 'POST') {
        $data = getJsonInput();
        $slug = trim($data['slug'] ?? '');
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

        // Resolve transfer by slug (preferred) or id (legacy)
        $transfer = null;
        if (!empty($slug) && preg_match('/^[a-f0-9]{32}$/', $slug)) {
            $stmt = $pdo->prepare("SELECT id, name, plate, review_stars FROM transfers WHERE slug = ?");
            $stmt->execute([$slug]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif ($id > 0) {
            $stmt = $pdo->prepare("SELECT id, name, plate, review_stars FROM transfers WHERE id = ?");
            $stmt->execute([$id]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($transfer) {
            // Duplicate check: prevent re-review
            if (!empty($transfer['review_stars'])) {
                jsonResponse(['status' => 'error', 'message' => 'Review already submitted']);
            }
            
            $transferId = $transfer['id'];
            try {
                // Save to customer_reviews table
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $pdo->prepare("INSERT INTO customer_reviews (order_id, customer_name, rating, comment, status, ip_address) VALUES (?, ?, ?, ?, 'pending', ?)")
                    ->execute([$transferId, $transfer['name'], $stars, $comment, $ip]);
                
                // Also update transfers table for backward compatibility
                $pdo->prepare("UPDATE transfers SET review_stars = ?, review_comment = ? WHERE id = ?")->execute([$stars, $comment, $transferId]);
                
                // Notify Manager
                sendFCM_V1($pdo, $service_account_file, "New Customer Review!", "{$transfer['name']} ({$transfer['plate']}) rated: $stars Stars");
            } catch (PDOException $e) {
                // Table might not exist, just save to transfers table
                $pdo->prepare("UPDATE transfers SET review_stars = ?, review_comment = ? WHERE id = ?")->execute([$stars, $comment, $transferId]);
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
            
            $plate = trim($data['plate']);

            // Try to fill phone automatically from vehicles DB if available
            $phoneFromVehicle = null;
            try {
                $vStmt = $pdo->prepare("SELECT phone FROM vehicles WHERE plate = ? LIMIT 1");
                $vStmt->execute([$plate]);
                $v = $vStmt->fetch(PDO::FETCH_ASSOC);
                if ($v && !empty($v['phone'])) {
                    $phoneFromVehicle = $v['phone'];
                }
            } catch (Exception $e) {
                // Ignore vehicle lookup failures - non-fatal
            }

            // Handle status and status_id
            $status = isset($data['status']) ? trim($data['status']) : 'New';
            $status_id = isset($data['status_id']) && is_numeric($data['status_id']) ? intval($data['status_id']) : null;
            
            // If status_id provided but no status name, look up the name
            if ($status_id && (!$status || $status === 'New')) {
                try {
                    $statusStmt = $pdo->prepare("SELECT name FROM statuses WHERE id = ?");
                    $statusStmt->execute([$status_id]);
                    $statusName = $statusStmt->fetchColumn();
                    if ($statusName) {
                        $status = $statusName;
                    }
                } catch (Exception $e) {}
            }
            
            // If status name provided but no ID, look up the ID
            if ($status && !$status_id) {
                try {
                    $statusStmt = $pdo->prepare("SELECT id FROM statuses WHERE name = ? AND type = 'case'");
                    $statusStmt->execute([$status]);
                    $foundId = $statusStmt->fetchColumn();
                    if ($foundId) {
                        $status_id = intval($foundId);
                    }
                } catch (Exception $e) {}
            }

            $stmt = $pdo->prepare("INSERT INTO transfers (plate, name, amount, franchise, rawText, phone, vehicle_make, vehicle_model, status, status_id, created_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->execute([
                $plate,
                trim($data['name']), 
                $amount,
                isset($data['franchise']) ? trim($data['franchise']) : '',
                isset($data['rawText']) ? trim($data['rawText']) : '',
                $phoneFromVehicle ?? (isset($data['phone']) ? trim($data['phone']) : null),
                isset($data['vehicleMake']) ? trim($data['vehicleMake']) : null,
                isset($data['vehicleModel']) ? trim($data['vehicleModel']) : null,
                $status,
                $status_id
            ]);

            $newId = $pdo->lastInsertId();
            
            jsonResponse(['status' => 'success', 'id' => $newId, 'message' => 'Transfer added successfully']);
        } catch (Exception $e) {
            error_log("Add transfer error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Failed to add transfer']);
        }
    }

    if ($action === 'update_transfer' && $method === 'POST') {
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['error' => 'Manager access required']);
            return;
        }
        $data = getJsonInput();
        $id = $data['id'] ?? null;

        if (!$id || empty($data)) {
            http_response_code(400);
            jsonResponse(['error' => 'Invalid ID or data']);
            return;
        }

        // Note: Column migrations have been moved to fix_db_all.php (run once on deployment)

        try {
            $field_map = [
                'name' => 'name',
                'plate' => 'plate',
                'amount' => 'amount',
                'case_type' => 'case_type',
                'status' => 'status',
                'status_id' => 'status_id',
                'phone' => 'phone',
                'serviceDate' => 'service_date',
                'dueDate' => 'due_date',
                'franchise' => 'franchise',
                'nachrebi_qty' => 'nachrebi_qty',
                'internalNotes' => 'internalNotes',
                'systemLogs' => 'systemLogs',
                'user_response' => 'user_response',
                'reviewStars' => 'review_stars',
                'reviewComment' => 'review_comment',
                'vehicleMake' => 'vehicle_make',
                'vehicleModel' => 'vehicle_model',
                'caseImages' => 'case_images',
                'repair_status' => 'repair_status',
                'repair_status_id' => 'repair_status_id',
                'assigned_mechanic' => 'assigned_mechanic',
                'repair_start_date' => 'repair_start_date',
                'repair_end_date' => 'repair_end_date',
                'repair_notes' => 'repair_notes',
                'repair_parts' => 'repair_parts',
                'repair_labor' => 'repair_labor',
                'repair_activity_log' => 'repair_activity_log',
                'parts_discount_percent' => 'parts_discount_percent',
                'services_discount_percent' => 'services_discount_percent',
                'global_discount_percent' => 'global_discount_percent',
                'slug' => 'slug',
                'vatEnabled' => 'vat_enabled',
                'vatAmount' => 'vat_amount'
            ];

            $update_fields = [];
            $params = [];

            // If status_id is provided, also update the text status for backward compatibility
            if (isset($data['status_id']) && is_numeric($data['status_id'])) {
                try {
                    $statusStmt = $pdo->prepare("SELECT name FROM statuses WHERE id = ?");
                    $statusStmt->execute([$data['status_id']]);
                    $statusName = $statusStmt->fetchColumn();
                    if ($statusName) {
                        $data['status'] = $statusName;
                    }
                } catch (Exception $e) {}
            }
            
            // If repair_status_id is provided, also update the text repair_status for backward compatibility
            if (isset($data['repair_status_id']) && is_numeric($data['repair_status_id'])) {
                try {
                    $statusStmt = $pdo->prepare("SELECT name FROM statuses WHERE id = ?");
                    $statusStmt->execute([$data['repair_status_id']]);
                    $statusName = $statusStmt->fetchColumn();
                    if ($statusName) {
                        $data['repair_status'] = $statusName;
                    }
                } catch (Exception $e) {}
            }

            foreach ($data as $key => $value) {
                if (array_key_exists($key, $field_map)) {
                    $db_field = $field_map[$key];
                    $update_fields[] = "`$db_field` = ?";

                    // Normalize incoming values:
                    // - arrays -> JSON
                    // - empty string, 'null', 'NULL', 'undefined' -> SQL NULL
                    // - otherwise use raw value
                    if (is_array($value)) {
                        $params[] = json_encode($value);
                    } else {
                        if ($value === '' || $value === null || strtolower((string)$value) === 'null' || strtolower((string)$value) === 'undefined') {
                            $params[] = null;
                        } else {
                            $params[] = $value;
                        }
                    }
                }
            }

            if (!empty($update_fields)) {
                $sql = "UPDATE transfers SET " . implode(', ', $update_fields) . " WHERE id = ?";
                $params[] = $id;
                
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                } catch (PDOException $e) {
                    error_log("Database error in update_transfer: " . $e->getMessage() . " SQL: $sql Params: " . json_encode($params));
                    http_response_code(500);
                    jsonResponse(['error' => 'Database error during update']);
                    return;
                }
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

    // --- BULK SCHEDULE NEW CASES ---
    if ($action === 'bulk_schedule_new' && $method === 'POST') {
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['success' => false, 'message' => 'Permission denied']);
        }

        $data = getJsonInput();
        $serviceDate = $data['service_date'] ?? '2026-01-05 10:00:00';
        $formattedDate = date('M d, Y H:i', strtotime($serviceDate));

        // Fetch New transfers (limited to first N if requested)
        $limit = intval($data['limit'] ?? 0);
        if ($limit > 0) {
            // Order by oldest first (created_at)
            $query = "SELECT id, name, plate, phone FROM transfers WHERE status = 'New' ORDER BY created_at ASC LIMIT " . $limit;
            $stmt = $pdo->prepare($query);
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("SELECT id, name, plate, phone FROM transfers WHERE status = 'New'");
            $stmt->execute();
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            jsonResponse(['success' => true, 'count' => 0, 'ids' => []]);
        }

        $pdo->beginTransaction();
        $scheduledIds = [];
        try {
            // Load schedule template
            $stmt = $pdo->prepare("SELECT content FROM sms_templates WHERE slug = 'schedule' AND is_active = 1");
            $stmt->execute();
            $template = $stmt->fetchColumn();
            if (!$template) {
                $template = "Hello {name}, your service is scheduled for {date}. Ref: {plate}. Confirm or reschedule: {link}";
            }

            $api_key = defined('SMS_API_KEY') ? SMS_API_KEY : "5c88b0316e44d076d4677a4860959ef71ce049ce704b559355568a362f40ade1";

            foreach ($rows as $r) {
                $pdo->prepare("UPDATE transfers SET status = 'Scheduled', status_id = (SELECT id FROM statuses WHERE name = 'Scheduled' AND type = 'case' LIMIT 1), service_date = ?, user_response = 'Pending' WHERE id = ?")
                    ->execute([$serviceDate, $r['id']]);

                // send SMS
                $link = "https://portal.otoexpress.ge/public_view.php?id=" . intval($r['id']);
                $smsText = str_replace(['{name}', '{plate}', '{date}', '{link}'], [$r['name'], $r['plate'], $formattedDate, $link], $template);
                $to = preg_replace('/\D+/', '', $r['phone']);
                if ($to) {
                    @file_get_contents("https://api.gosms.ge/api/sendsms?api_key=$api_key&to=$to&from=OTOMOTORS&text=" . urlencode($smsText));
                }

                // append system log
                $log_message = "Auto-scheduled for $formattedDate and notification sent.";
                $pdo->prepare("UPDATE transfers SET system_logs = JSON_ARRAY_APPEND(COALESCE(system_logs, '[]'), '$', CAST(? AS JSON)) WHERE id = ?")
                    ->execute([json_encode(['timestamp' => date('Y-m-d H:i:s'), 'message' => $log_message]), $r['id']]);

                $scheduledIds[] = $r['id'];
            }

            $pdo->commit();

            // Notify managers via FCM
            $title = "Bulk Schedule Completed";
            $body = count($scheduledIds) . " cases scheduled for $formattedDate";
            sendFCM_V1($pdo, $service_account_file, $title, $body);

            jsonResponse(['success' => true, 'count' => count($scheduledIds), 'ids' => $scheduledIds]);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("bulk_schedule_new failed: " . $e->getMessage());
            http_response_code(500);
            jsonResponse(['success' => false, 'message' => 'Internal server error']);
        }
    }

    // --- RESEND SCHEDULE SMS TO UNCONFIRMED SCHEDULED CASES ---
    if ($action === 'resend_schedule_sms' && $method === 'POST') {
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['success' => false, 'message' => 'Permission denied']);
        }

        // Find all Scheduled cases that are NOT Confirmed
        $stmt = $pdo->prepare("SELECT id, name, plate, phone, service_date FROM transfers WHERE status = 'Scheduled' AND user_response != 'Confirmed' AND phone IS NOT NULL AND phone != ''");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            jsonResponse(['success' => true, 'count' => 0, 'message' => 'No unconfirmed scheduled cases found']);
        }

        try {
            // Load schedule template
            $stmt = $pdo->prepare("SELECT content FROM sms_templates WHERE slug = 'schedule' AND is_active = 1");
            $stmt->execute();
            $template = $stmt->fetchColumn();
            if (!$template) {
                $template = "Hello {name}, your service is scheduled for {date}. Ref: {plate}. Confirm or reschedule: {link}";
            }

            $api_key = defined('SMS_API_KEY') ? SMS_API_KEY : "5c88b0316e44d076d4677a4860959ef71ce049ce704b559355568a362f40ade1";
            $sentCount = 0;

            foreach ($rows as $r) {
                if (empty($r['service_date'])) continue;

                $formattedDate = date('M d, Y H:i', strtotime($r['service_date']));
                $link = "https://portal.otoexpress.ge/public_view.php?id=" . intval($r['id']);
                $smsText = str_replace(
                    ['{name}', '{plate}', '{date}', '{link}'],
                    [$r['name'], $r['plate'], $formattedDate, $link],
                    $template
                );

                $to = preg_replace('/\D+/', '', $r['phone']);
                if ($to) {
                    @file_get_contents("https://api.gosms.ge/api/sendsms?api_key=$api_key&to=$to&from=OTOMOTORS&text=" . urlencode($smsText));
                    $sentCount++;
                }
            }

            // Send FCM notification to managers
            $title = "Bulk SMS Resend Completed";
            $body = "$sentCount schedule SMS(s) resent to unconfirmed cases";
            sendFCM_V1($pdo, $service_account_file, $title, $body);

            jsonResponse(['success' => true, 'count' => $sentCount]);
        } catch (Exception $e) {
            error_log("resend_schedule_sms failed: " . $e->getMessage());
            http_response_code(500);
            jsonResponse(['success' => false, 'message' => 'Internal server error']);
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

    if ($action === 'confirm_appointment' && $method === 'POST') {
        $id = intval($_GET['id'] ?? 0);
        $data = getJsonInput();

        if ($id > 0) {
            try {
                // Update user_response to Confirmed
                $pdo->prepare("UPDATE transfers SET user_response = 'Confirmed' WHERE id = ?")
                    ->execute([$id]);

                // Log the action
                $stmt = $pdo->prepare("SELECT internalNotes FROM transfers WHERE id = ?");
                $stmt->execute([$id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $notes = json_decode($result['internalNotes'] ?? '[]', true);
                $notes[] = [
                    'text' => 'Appointment manually confirmed by manager',
                    'authorName' => 'System',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ];
                
                $pdo->prepare("UPDATE transfers SET internalNotes = ? WHERE id = ?")
                    ->execute([json_encode($notes), $id]);

                jsonResponse(['status' => 'success', 'success' => true, 'message' => 'Appointment confirmed']);
            } catch (Exception $e) {
                error_log("confirm_appointment error: " . $e->getMessage());
                http_response_code(500);
                jsonResponse(['status' => 'error', 'success' => false, 'message' => 'Database error']);
            }
        } else {
            jsonResponse(['status' => 'error', 'success' => false, 'message' => 'Invalid ID']);
        }
    }

    // --- MANAGER ACTIONS ---

    if ($action === 'get_transfers' && $method === 'GET') {
        // NOTE: Schema migrations moved to fix_db_all.php for performance
        // Run fix_db_all.php once after deployment to ensure all columns exist

        // Check if statuses table exists for JOIN
        $statusesExist = false;
        try {
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'statuses'");
            $statusesExist = $tableCheck->rowCount() > 0;
        } catch (Exception $e) {}

        if ($statusesExist) {
            // Use JOIN to get status names from IDs, with fallback to text columns
            $stmt = $pdo->prepare("
                SELECT t.*, 
                    t.service_date as serviceDate, 
                    t.user_response as user_response, 
                    t.review_stars as reviewStars, 
                    t.review_comment as reviewComment, 
                    t.reschedule_date as rescheduleDate, 
                    t.reschedule_comment as rescheduleComment, 
                    t.link_opened_at as linkOpenedAt, 
                    t.operatorComment,
                    COALESCE(cs.name, t.status) as status,
                    t.status_id,
                    cs.color as status_color,
                    cs.bg_color as status_bg_color,
                    COALESCE(rs.name, t.repair_status) as repair_status,
                    t.repair_status_id,
                    rs.color as repair_status_color,
                    rs.bg_color as repair_status_bg_color
                FROM transfers t
                LEFT JOIN statuses cs ON t.status_id = cs.id AND cs.type = 'case'
                LEFT JOIN statuses rs ON t.repair_status_id = rs.id AND rs.type = 'repair'
                ORDER BY t.created_at DESC
            ");
        } else {
            // Fallback to old query without JOIN
            $stmt = $pdo->prepare("SELECT *, service_date as serviceDate, user_response as user_response, review_stars as reviewStars, review_comment as reviewComment, reschedule_date as rescheduleDate, reschedule_comment as rescheduleComment, link_opened_at as linkOpenedAt, operatorComment, repair_status FROM transfers ORDER BY created_at DESC");
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Build name→id maps for resolving missing IDs from text columns
        $repairNameToId = [];
        $caseNameToId = [];
        try {
            $mapStmt = $pdo->query("SELECT id, type, name FROM statuses WHERE is_active = 1");
            foreach ($mapStmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
                if ($s['type'] === 'repair') $repairNameToId[trim($s['name'])] = intval($s['id']);
                if ($s['type'] === 'case') $caseNameToId[trim($s['name'])] = intval($s['id']);
            }
        } catch (Exception $e) {}

        foreach ($rows as &$row) {
            $row['internalNotes'] = json_decode($row['internalNotes'] ?? '[]');
            $row['systemLogs'] = json_decode($row['systemLogs'] ?? '[]');

            // Resolve missing repair_status_id from text repair_status
            if (empty($row['repair_status_id']) && !empty($row['repair_status'])) {
                $rText = trim($row['repair_status']);
                if (isset($repairNameToId[$rText])) {
                    $row['repair_status_id'] = $repairNameToId[$rText];
                }
            }
            // Resolve missing status_id from text status
            if (empty($row['status_id']) && !empty($row['status'])) {
                $sText = trim($row['status']);
                if (isset($caseNameToId[$sText])) {
                    $row['status_id'] = $caseNameToId[$sText];
                }
            }
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

    // --- GET TRANSFERS FOR PARTS COLLECTION (exclude Completed) ---
    if ($action === 'get_transfers_for_parts' && $method === 'GET') {
        $stmt = $pdo->prepare("SELECT id, plate, name, status FROM transfers WHERE status != 'Completed' ORDER BY created_at DESC");
        $stmt->execute();
        $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(['transfers' => $transfers]);
    }

    // Get backlog cases (repair_stage IS NULL)
    if ($action === 'get_backlog' && $method === 'GET') {
        try {
            $stmt = $pdo->prepare("SELECT id, plate, vehicle_make, vehicle_model, repair_stage, repair_assignments, stage_timers, stage_statuses, urgent FROM transfers WHERE (repair_stage IS NULL OR repair_stage = 'backlog') AND status NOT IN ('Completed', 'Issue') ORDER BY id DESC");
            $stmt->execute();
            $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cases as &$case) {
                $case['repair_assignments'] = json_decode($case['repair_assignments'] ?? '{}', true);
                $case['stage_timers'] = json_decode($case['stage_timers'] ?? '{}', true);
                $case['stage_statuses'] = json_decode($case['stage_statuses'] ?? '{}', true);
            }
            jsonResponse(['cases' => $cases]);
        } catch (Exception $e) {
            error_log("Get cases error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error']);
        }
    }

    // Get single transfer with logs and work times
    if ($action === 'get_transfer' && $method === 'GET') {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) jsonResponse(['status' => 'error', 'message' => 'Invalid id']);
        $stmt = $pdo->prepare("SELECT id, plate, name, phone, amount, franchise, nachrebi_qty, status, status_id, case_type, service_date, vehicle_make, vehicle_model, repair_parts, repair_labor, repair_status, repair_status_id FROM transfers WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) jsonResponse(['status' => 'error', 'message' => 'Not found']);
        
        // Robust JSON decoding - handle both string and already-parsed data
        if (!empty($row['repair_parts'])) {
            if (is_string($row['repair_parts'])) {
                $decoded = json_decode($row['repair_parts'], true);
                $row['repair_parts'] = is_array($decoded) ? $decoded : [];
            } elseif (!is_array($row['repair_parts'])) {
                $row['repair_parts'] = [];
            }
        } else {
            $row['repair_parts'] = [];
        }
        
        if (!empty($row['repair_labor'])) {
            if (is_string($row['repair_labor'])) {
                $decoded = json_decode($row['repair_labor'], true);
                $row['repair_labor'] = is_array($decoded) ? $decoded : [];
            } elseif (!is_array($row['repair_labor'])) {
                $row['repair_labor'] = [];
            }
        } else {
            $row['repair_labor'] = [];
        }
        
        jsonResponse($row);
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
                $requiredSms = [
                    'workflow_stages' => "JSON DEFAULT NULL",
                    'is_active' => "BOOLEAN DEFAULT 1",
                    'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
                    'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
                ];
                $checkStmtSms = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'sms_templates' AND COLUMN_NAME = ?");
                foreach ($requiredSms as $col => $def) {
                    $checkStmtSms->execute([DB_NAME, $col]);
                    if ($checkStmtSms->fetchColumn() == 0) {
                        $pdo->exec("ALTER TABLE sms_templates ADD COLUMN `$col` $def");
                    }
                }
            } catch (Exception $alterError) {
                // Continue anyway - columns might already exist
            }

            // Check if we can query the table
            try {
                $testStmt = $pdo->query("SELECT COUNT(*) as count FROM sms_templates");
                $testResult = $testStmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $testError) {
                http_response_code(500);
                jsonResponse(['error' => 'Cannot access SMS templates table']);
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
            jsonResponse(['error' => 'Failed to save templates']);
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
            jsonResponse(['error' => 'Database error']);
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
        
        if (!in_array($role, ['admin', 'manager', 'viewer', 'technician', 'operator'])) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid role']);
        }
        
        try {
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
        } catch (Exception $e) {
            error_log("Create user error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
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
        if ($role && in_array($role, ['admin', 'manager', 'viewer', 'technician', 'operator'])) {
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
        $collection_type = in_array(($data['collection_type'] ?? ''), ['local', 'order']) ? $data['collection_type'] : 'local';

        if (!$transfer_id || empty($parts_list)) {
            http_response_code(400);
            jsonResponse(['error' => 'Transfer ID and parts list are required']);
        }

        // Calculate total cost
        $total_cost = 0;
        foreach ($parts_list as $part) {
            $total_cost += ($part['quantity'] ?? 0) * ($part['price'] ?? 0);
        }

        $stmt = $pdo->prepare("INSERT INTO parts_collections (transfer_id, parts_list, total_cost, assigned_manager_id, currency, description, collection_type) VALUES (?, ?, ?, ?, 'GEL', ?, ?)");
        $stmt->execute([$transfer_id, json_encode($parts_list), $total_cost, $assigned_manager_id, $description, $collection_type]);
        $new_collection_id = $pdo->lastInsertId();

        // --- NEW: UPDATE SUGGESTIONS ---
        update_suggestions_from_list($pdo, $parts_list);

        // --- NEW: Send SMS notification to customer depending on collection_type ---
        try {
            // Fetch transfer contact
            $stmt = $pdo->prepare("SELECT name, plate, phone FROM transfers WHERE id = ?");
            $stmt->execute([$transfer_id]);
            $tr = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($tr && !empty($tr['phone'])) {
                // Determine template slug
                $slug = ($collection_type === 'order') ? 'parts_ordered' : 'parts_request_local';

                // Try to fetch template from DB
                $stmt = $pdo->prepare("SELECT content FROM sms_templates WHERE slug = ? AND is_active = 1");
                $stmt->execute([$slug]);
                $template = $stmt->fetchColumn();

                // Fallback messages
                if (!$template) {
                    if ($collection_type === 'order') {
                        $template = "თქვენი ავტომობილისთვის საჭირო დეტალები შეკვეთილია. დამატებითი დეტალებისათვის ახლავე დაგიკავშირდებით.";
                    } else {
                        $template = "გამარჯობა, მიმდინარეობს თქვენი ავტომობილის აღსადგენად საჭირო დეტალების შეგროვება. სერვისთან დაკავშირებულ დეტალებს, უახლოეს მომავალში მიიღებთ.";
                    }
                }

                $to = preg_replace('/\D+/', '', $tr['phone']);
                $api_key = defined('SMS_API_KEY') ? SMS_API_KEY : "5c88b0316e44d076d4677a4860959ef71ce049ce704b559355568a362f40ade1";
                @file_get_contents("https://api.gosms.ge/api/sendsms?api_key=$api_key&to=$to&from=OTOMOTORS&text=" . urlencode($template));
            }
        } catch (Exception $e) {
            error_log("Failed to send parts collection SMS for collection $new_collection_id: " . $e->getMessage());
        }

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
            // 1. Update transfer status with status_id
            $update_transfer_stmt = $pdo->prepare("UPDATE transfers SET status = 'Parts Arrived', status_id = (SELECT id FROM statuses WHERE name = 'Parts Arrived' AND type = 'case' LIMIT 1) WHERE id = ?");
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

    // --- PAYMENTS ENDPOINTS ---
    if ($action === 'create_payment' && $method === 'POST') {
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['error' => 'Manager access required']);
        }
        // Try JSON body first, fallback to $_POST
        $data = getJsonInput();
        if (empty($data)) $data = $_POST;

        // Normalize inputs (accept multiple possible keys)
        $transfer_id_raw = $data['transfer_id'] ?? $data['transferId'] ?? ($_POST['transfer_id'] ?? 0);
        $amount_raw = $data['amount'] ?? $data['Amount'] ?? ($_POST['amount'] ?? 0);

        // Clean numeric values (accept comma as decimal separator)
        $transfer_id = intval($transfer_id_raw);
        $amount = floatval(str_replace(',', '.', trim((string)$amount_raw)));

        $methodType = strtolower(trim($data['method'] ?? $data['payment_method'] ?? 'cash'));
        if (!in_array($methodType, ['cash','transfer'])) $methodType = 'cash';
        $reference = trim($data['reference'] ?? $data['ref'] ?? '');
        $notes = trim($data['notes'] ?? $data['note'] ?? '');

        // Debug: log request for easier troubleshooting when invalid
        if (!$transfer_id || $amount <= 0) {
            error_log('create_payment validation failed - data: ' . json_encode(['data' => $data, '_POST' => $_POST, 'raw_input' => file_get_contents('php://input')]));
            http_response_code(400);
            jsonResponse(['error' => 'transfer_id and positive amount are required']);
        }

        // Note: Table/column migrations have been moved to fix_db_all.php

        $stmt = $pdo->prepare("SELECT id, amount, COALESCE(amount_paid,0) as amount_paid FROM transfers WHERE id = ? LIMIT 1");
        $stmt->execute([$transfer_id]);
        $tr = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tr) {
            http_response_code(404);
            jsonResponse(['error' => 'Transfer not found']);
        }
        $recorded_by = getCurrentUserId();

        // Use transaction to keep payment insert and transfer update atomic
        $pdo->beginTransaction();
        try {
            $sql = "INSERT INTO payments (transfer_id, amount, method, reference, recorded_by, notes, currency, paid_at) VALUES (?, ?, ?, ?, ?, ?, 'GEL', NOW())";
            $insert = $pdo->prepare($sql);
            $insert->execute([$transfer_id, $amount, $methodType, $reference, $recorded_by, $notes]);
            $payment_id = $pdo->lastInsertId();

            // Update transfer paid totals
            $new_paid = floatval($tr['amount_paid']) + $amount;
            $status = (!is_null($tr['amount']) && floatval($new_paid) >= floatval($tr['amount'])) ? 'paid' : ($new_paid > 0 ? 'partial' : 'unpaid');
            $updateStmt = $pdo->prepare("UPDATE transfers SET amount_paid = ?, payment_status = ?, last_payment_at = NOW() WHERE id = ?");
            $updateStmt->execute([number_format($new_paid,2,'.',''), $status, $transfer_id]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('create_payment failed: ' . $e->getMessage());
            http_response_code(500);
            jsonResponse(['error' => 'Failed to save payment']);
            return;
        }

        $log_stmt = $pdo->prepare("UPDATE transfers SET system_logs = JSON_ARRAY_APPEND(COALESCE(system_logs, '[]'), '$', CAST(? AS JSON)) WHERE id = ?");
        $log_stmt->execute([json_encode(['timestamp' => date('Y-m-d H:i:s'), 'type' => 'payment', 'amount' => floatval($amount), 'method' => $methodType, 'reference' => $reference, 'user' => $recorded_by, 'message' => "Payment recorded: {$amount} via {$methodType}"]), $transfer_id]);

        jsonResponse(['status' => 'success', 'payment_id' => $payment_id, 'new_amount_paid' => $new_paid, 'payment_status' => $status]);
    }

    if ($action === 'get_payments' && $method === 'GET') {
        $transfer_id = intval($_GET['transfer_id'] ?? 0);
        if (!$transfer_id) {
            http_response_code(400);
            jsonResponse(['error' => 'transfer_id is required']);
        }

        // Detect columns present in payments table to avoid SQL errors on legacy schemas
        $colStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'payments'");
        $colStmt->execute([DB_NAME]);
        $paymentsCols = array_column($colStmt->fetchAll(PDO::FETCH_COLUMN), 0);
        $has_recorded_by = in_array('recorded_by', $paymentsCols);
        $has_paid_at = in_array('paid_at', $paymentsCols);
        $has_payment_date = in_array('payment_date', $paymentsCols);

        if ($has_recorded_by) {
            $orderBy = $has_paid_at ? 'p.paid_at DESC' : ($has_payment_date ? 'p.payment_date DESC' : 'p.id DESC');
            $stmt = $pdo->prepare("SELECT p.*, u.username as recorded_by_username FROM payments p LEFT JOIN users u ON u.id = p.recorded_by WHERE p.transfer_id = ? ORDER BY $orderBy, p.id DESC");
            $stmt->execute([$transfer_id]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $orderBy = $has_paid_at ? 'p.paid_at DESC' : ($has_payment_date ? 'p.payment_date DESC' : 'p.id DESC');
            $stmt = $pdo->prepare("SELECT p.* FROM payments p WHERE p.transfer_id = ? ORDER BY $orderBy, p.id DESC");
            $stmt->execute([$transfer_id]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as total_paid FROM payments WHERE transfer_id = ?");
        $sumStmt->execute([$transfer_id]);
        $total_paid = $sumStmt->fetchColumn();
        jsonResponse(['payments' => $payments, 'total_paid' => floatval($total_paid)]);
    }

    if ($action === 'delete_payment' && $method === 'POST') {
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['error' => 'Manager access required']);
        }
        $data = getJsonInput();
        if (empty($data)) $data = $_POST;

        $payment_id = intval($data['payment_id'] ?? 0);
        if (!$payment_id) {
            http_response_code(400);
            jsonResponse(['error' => 'payment_id is required']);
        }

        // Get payment details before deletion
        $stmt = $pdo->prepare("SELECT transfer_id, amount FROM payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$payment) {
            http_response_code(404);
            jsonResponse(['error' => 'Payment not found']);
        }

        $transfer_id = $payment['transfer_id'];
        $deleted_amount = floatval($payment['amount']);

        // Use transaction for atomic delete + update
        $pdo->beginTransaction();
        try {
            $deleteStmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
            $deleteStmt->execute([$payment_id]);

            // Update transfer paid totals
            $stmt = $pdo->prepare("SELECT amount, COALESCE(amount_paid,0) as amount_paid FROM transfers WHERE id = ?");
            $stmt->execute([$transfer_id]);
            $tr = $stmt->fetch(PDO::FETCH_ASSOC);
            $new_paid = max(0, floatval($tr['amount_paid']) - $deleted_amount);
            $status = (!is_null($tr['amount']) && floatval($new_paid) >= floatval($tr['amount'])) ? 'paid' : ($new_paid > 0 ? 'partial' : 'unpaid');

            $updateStmt = $pdo->prepare("UPDATE transfers SET amount_paid = ?, payment_status = ? WHERE id = ?");
            $updateStmt->execute([number_format($new_paid,2,'.',''), $status, $transfer_id]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('delete_payment failed: ' . $e->getMessage());
            http_response_code(500);
            jsonResponse(['error' => 'Failed to delete payment']);
            return;
        }

        // Log the deletion
        $log_stmt = $pdo->prepare("UPDATE transfers SET system_logs = JSON_ARRAY_APPEND(COALESCE(system_logs, '[]'), '$', CAST(? AS JSON)) WHERE id = ?");
        $log_stmt->execute([json_encode(['timestamp' => date('Y-m-d H:i:s'), 'type' => 'payment_deleted', 'amount' => $deleted_amount, 'user' => getCurrentUserId(), 'message' => "Payment deleted: {$deleted_amount}"]), $transfer_id]);

        jsonResponse(['status' => 'success', 'new_amount_paid' => $new_paid, 'payment_status' => $status]);
    }

    // --- DELETE TRANSFER ENDPOINT ---
    if ($action === 'delete_transfer' && $method === 'POST') {
        // Check permissions - managers and admins can delete transfers
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['status' => 'error', 'message' => 'Manager access required to delete transfers']);
        }

        $id = intval($_GET['id'] ?? 0);

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
            jsonResponse(['status' => 'error', 'message' => 'Failed to delete transfer']);
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
            jsonResponse(['status' => 'error', 'message' => 'Failed to sync vehicle']);
        }
    }

    // --- DELETE VEHICLE ENDPOINT ---
    if ($action === 'delete_vehicle' && $method === 'POST') {
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['status' => 'error', 'message' => 'Manager access required to delete vehicles']);
        }

        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            jsonResponse(['status' => 'error', 'message' => 'Vehicle ID is required']);
        }

        try {
            $stmt = $pdo->prepare("SELECT id, plate FROM vehicles WHERE id = ?");
            $stmt->execute([$id]);
            $v = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$v) {
                jsonResponse(['status' => 'error', 'message' => 'Vehicle not found']);
            }

            $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ?");
            $stmt->execute([$id]);

            jsonResponse(['status' => 'deleted', 'message' => 'Vehicle deleted successfully']);
        } catch (Exception $e) {
            error_log("Delete vehicle error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Failed to delete vehicle']);
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

    // --------------------------------------------------
    // GET TECHNICIANS ENDPOINT (for repair management)
    // --------------------------------------------------
    if ($action === 'get_technicians' && $method === 'GET') {
        $stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE role = 'technician' AND status = 'active' ORDER BY full_name");
        $stmt->execute();
        $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse(['technicians' => $technicians]);
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
            
            // Initialize log content
            $logContent = "--- PDF PARSE ATTEMPT: " . date('Y-m-d H:i:s') . " ---\n" . $text . "\n--- END ---\n\n";

            // Define Georgian keywords and section delimiters (with flexible matching)
            $partsHeader = 'დეტალების ჩამონათვალი';
            $laborHeader = 'მომსახურების ჩამონათვალი';
            $sectionEnd = 'ჯამი (ლარი)';

            // Try to find headers with more flexible matching
            $partsHeaderPos = strpos($text, $partsHeader);
            if ($partsHeaderPos === false) {
                // Try without exact spacing
                $partsHeaderPos = strpos($text, 'დეტალების');
                // $logContent .= "FALLBACK: Parts header 'დეტალების' at position: $partsHeaderPos\n";
            }
            
            $laborHeaderPos = strpos($text, $laborHeader);
            if ($laborHeaderPos === false) {
                // Try without exact spacing
                $laborHeaderPos = strpos($text, 'მომსახურების');
                // $logContent .= "FALLBACK: Labor header 'მომსახურების' at position: $laborHeaderPos\n";
            }

            // Debug logging
            // $logContent .= "LOOKING FOR HEADERS:\n";
            // $logContent .= "Parts header: '$partsHeader' at position: $partsHeaderPos\n";
            // $logContent .= "Labor header: '$laborHeader' at position: $laborHeaderPos\n";
            // $logContent .= "Section end: '$sectionEnd' found " . count($endMarkers) . " times at positions: " . implode(', ', $endMarkers) . "\n\n";

            // --- DATA-DRIVEN PARSING FUNCTIONS ---

            // Parses the Parts section - simple line-by-line processing
            function parsePartsSection($textBlock) {

                global $logContent;
                $logContent .= "PARTS SECTION TEXT:\n'$textBlock'\n--- END PARTS SECTION ---\n\n";

                $lines = preg_split('/\r?\n/', $textBlock);
                $items = [];
                $currentItem = null;

                foreach ($lines as $line) {
                    $line = trim($line);
                    $logContent .= "PROCESSING PARTS LINE: '$line'\n";
                    if (empty($line)) continue;

                    // Skip header lines
                    if (strpos($line, 'რაოდენობა') !== false && strpos($line, 'სტატუსი') !== false) {
                        $logContent .= "SKIPPING HEADER\n";
                        continue;
                    }

                    // Try to match: name, quantity, status, price (all on one line, separated by spaces or tabs)
                    if (preg_match('/^(.+?)\s{1,}(\d+)\s+([^\s]+)\s*([\d,.]+)$/u', $line, $matches)) {
                        $name = trim($matches[1]);
                        $quantity = (int)$matches[2];
                        $status = $matches[3];
                        $price = (float)str_replace(',', '', $matches[4]);
                        $items[] = [
                            'name' => $name,
                            'quantity' => $quantity,
                            'price' => $price,
                            'type' => 'part',
                        ];
                        $logContent .= "PARSED SINGLE-LINE PARTS ITEM: '$name' qty=$quantity price=$price\n";
                        $currentItem = null;
                        continue;
                    }

                    // Check if this line contains a quantity (ends with number followed by status and price)
                    if (preg_match('/(\d+)\s+([^\s]+)\s*([\d,.]+)$/', $line, $matches)) {
                        // This is a data line: quantity, status, price
                        $quantity = (int)$matches[1];
                        $status = $matches[2];
                        $price = (float)str_replace(',', '', $matches[3]);

                        if ($currentItem !== null) {
                            // Complete the previous item
                            $currentItem['quantity'] = $quantity;
                            $currentItem['price'] = $price;
                            $items[] = $currentItem;
                            $logContent .= "COMPLETED PARTS ITEM: '" . $currentItem['name'] . "' qty=$quantity price=$price\n";
                            $currentItem = null;
                        }
                        continue;
                    }

                    // This is part of an item name
                    if ($currentItem === null) {
                        $currentItem = ['name' => $line, 'type' => 'part'];
                        $logContent .= "STARTED NEW PARTS ITEM: '$line'\n";
                    } else {
                        $currentItem['name'] .= ' ' . $line;
                        $logContent .= "EXTENDED PARTS ITEM: '" . $currentItem['name'] . "'\n";
                    }
                }

                $logContent .= "FINAL PARTS ITEMS: " . count($items) . "\n";
                foreach ($items as $item) {
                    $logContent .= "- " . $item['name'] . " (qty: " . $item['quantity'] . ", price: " . $item['price'] . ")\n";
                }
                $logContent .= "\n";

                return $items;
            }

            // Parses the Labor section - simple line-by-line processing
            function parseLaborSection($textBlock) {
                global $logContent;
                $logContent .= "LABOR SECTION TEXT:\n'$textBlock'\n--- END LABOR SECTION ---\n\n";

                $lines = preg_split('/\r?\n/', $textBlock);
                $items = [];
                $multiLineBuffer = [];

                foreach ($lines as $line) {
                    $line = trim($line);
                    $logContent .= "PROCESSING LABOR LINE: '$line'\n";
                    if ($line === '' || stripos($line, 'ფასი(ლარი)') !== false) {
                        $logContent .= "SKIPPING HEADER\n";
                        continue;
                    }
                    // Single-line: name\tprice
                    if (strpos($line, "\t") !== false) {
                        list($name, $price) = array_map('trim', explode("\t", $line, 2));
                        if ($name !== '' && is_numeric($price)) {
                            $items[] = [
                                'name' => $name,
                                'price' => $price,
                                'type' => 'labor',
                                'quantity' => 1
                            ];
                            $logContent .= "CREATED SINGLE-LINE LABOR ITEM: '$name' = $price\n";
                        }
                        $multiLineBuffer = [];
                        continue;
                    }
                    // Multi-line: accumulate name lines, then price
                    if (is_numeric($line)) {
                        if (!empty($multiLineBuffer)) {
                            $fullName = implode(' ', $multiLineBuffer);
                            $items[] = [
                                'name' => $fullName,
                                'price' => $line,
                                'type' => 'labor',
                                'quantity' => 1
                            ];
                            $logContent .= "COMPLETED MULTI-LINE LABOR ITEM: '$fullName' = $line\n";
                            $multiLineBuffer = [];
                        }
                        continue;
                    }
                    // Start or extend multi-line item
                    $multiLineBuffer[] = $line;
                    if (count($multiLineBuffer) === 1) {
                        $logContent .= "STARTED NEW MULTI-LINE ITEM: '$line'\n";
                    } else {
                        $logContent .= "EXTENDED MULTI-LINE ITEM: '" . implode(' ', $multiLineBuffer) . "'\n";
                    }
                }

                $logContent .= "FINAL LABOR ITEMS: " . count($items) . "\n";
                foreach ($items as $item) {
                    $logContent .= "- " . $item['name'] . " (" . $item['price'] . ")\n";
                }
                $logContent .= "\n";

                return $items;
            }

            // --- MAIN LOGIC ---

            // Isolate the text for each section
            $partsTextBlock = '';
            $laborTextBlock = '';

            $partsHeaderPos = strpos($text, $partsHeader);
            $laborHeaderPos = strpos($text, $laborHeader);
            
            // Find all occurrences of section end marker
            $endMarkers = [];
            $offset = 0;
            while (($pos = strpos($text, $sectionEnd, $offset)) !== false) {
                $endMarkers[] = $pos;
                $offset = $pos + 1;
            }

            // Extract parts section: from parts header to first "ჯამი (ლარი)" after it
            $partsStart = strpos($text, $partsHeader);
            if ($partsStart !== false) {
                $partsStart += strlen($partsHeader);
                $partsEnd = strpos($text, $sectionEnd, $partsStart);
                if ($partsEnd !== false) {
                    $partsTextBlock = trim(substr($text, $partsStart, $partsEnd - $partsStart));
                }
            }

            // Extract labor section: from labor header to first "ჯამი (ლარი)" after it
            $laborStart = strpos($text, $laborHeader);
            if ($laborStart !== false) {
                $laborStart += strlen($laborHeader);
                $laborEnd = strpos($text, $sectionEnd, $laborStart);
                if ($laborEnd !== false) {
                    $laborTextBlock = trim(substr($text, $laborStart, $laborEnd - $laborStart));
                }
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
                    if (stripos($name, $header) !== false) {
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

            // Write debug log
            file_put_contents(__DIR__ . '/error_log', $logContent, FILE_APPEND);

            if (empty($items)) {
                 jsonResponse(['success' => false, 'error' => 'Could not automatically detect any items based on the specified format. Please add them manually.']);
            } else {
                 jsonResponse(['success' => true, 'items' => array_values($items)]); // Re-index array
            }

        } catch (Exception $e) {
            error_log("Parse PDF error: " . $e->getMessage());
            jsonResponse(['success' => false, 'error' => 'Failed to parse PDF']);
        }
    }

    // --- SEND SMS ENDPOINT ---
    if ($action === 'send_sms' && $method === 'POST') {
        $data = getJsonInput();
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

    // --- WORKFLOW ENDPOINTS ---
    if ($action === 'update_repair_stage' && $method === 'POST') {
        $data = getJsonInput();
        $case_id = intval($data['case_id'] ?? 0);
        $stage = trim($data['stage'] ?? '');

        if ($case_id <= 0 || empty($stage)) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid case ID or stage']);
        }

        // Validate stage exists
        $valid_stages = getValidWorkflowStages();
        if (!in_array($stage, $valid_stages)) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid stage']);
        }

        // Handle backlog - set repair_stage to NULL
        $db_stage = ($stage === 'backlog') ? null : $stage;

        try {
            // Get full current state so we can record work time for the stage we're leaving
            $stmt = $pdo->prepare("SELECT repair_stage, repair_assignments, stage_timers, stage_statuses, work_times FROM transfers WHERE id = ?");
            $stmt->execute([$case_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) jsonResponse(['status' => 'error', 'message' => 'Case not found']);

            $oldStage = $row['repair_stage'];
            $assignments = json_decode($row['repair_assignments'] ?: '{}', true);
            $timers = json_decode($row['stage_timers'] ?: '{}', true);
            $statuses = json_decode($row['stage_statuses'] ?: '{}', true);
            $workTimes = json_decode($row['work_times'] ?: '{}', true);

            $nowMs = time() * 1000;

            // If leaving a stage with an active timer and an assigned technician, record elapsed work time
            if ($oldStage && !empty($timers[$oldStage]) && !empty($assignments[$oldStage])) {
                $techId = $assignments[$oldStage];
                $elapsed = $nowMs - intval($timers[$oldStage]);
                if (!isset($workTimes[$oldStage])) $workTimes[$oldStage] = [];
                if (!isset($workTimes[$oldStage][$techId])) $workTimes[$oldStage][$techId] = 0;
                $workTimes[$oldStage][$techId] += $elapsed;

                // Append work_time log
                $logEntry = json_encode(['type' => 'work_time', 'stage' => $oldStage, 'tech' => $techId, 'duration_ms' => $elapsed, 'timestamp' => $nowMs]);
                $log_stmt = $pdo->prepare("UPDATE transfers SET system_logs = JSON_ARRAY_APPEND(COALESCE(system_logs, '[]'), '$', CAST(? AS JSON)) WHERE id = ?");
                $log_stmt->execute([$logEntry, $case_id]);
            }

            // Clear timers/statuses/assignment for the old stage since assignments are stage-specific
            if ($oldStage) {
                unset($timers[$oldStage]);
                unset($statuses[$oldStage]);
                unset($assignments[$oldStage]);
            }

            // Move case to new stage and persist updated timers/statuses/assignments and work times
            $stmt = $pdo->prepare("UPDATE transfers SET repair_stage = ?, repair_assignments = ?, stage_timers = ?, stage_statuses = ?, work_times = ? WHERE id = ?");
            $stmt->execute([$db_stage, json_encode($assignments), json_encode($timers), json_encode($statuses), json_encode($workTimes), $case_id]);

            if ($stmt->rowCount() > 0) {
                // Append a move log
                $moveLog = json_encode(['type' => 'move', 'from' => $oldStage, 'to' => $stage, 'by' => getCurrentUserId(), 'timestamp' => $nowMs]);
                $log_stmt = $pdo->prepare("UPDATE transfers SET system_logs = JSON_ARRAY_APPEND(COALESCE(system_logs, '[]'), '$', CAST(? AS JSON)) WHERE id = ?");
                $log_stmt->execute([$moveLog, $case_id]);

                jsonResponse(['status' => 'success']);
            } else {
                jsonResponse(['status' => 'error', 'message' => 'Case not found or no changes made']);
            }
        } catch (Exception $e) {
            error_log("Assign manager error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error']);
        }
    }

    if ($action === 'assign_technician' && $method === 'POST') {
        $data = getJsonInput();
        $case_id = intval($data['case_id'] ?? 0);
        $stage = trim($data['stage'] ?? '');
        $technician_id = intval($data['technician_id'] ?? 0);

        if ($case_id <= 0 || empty($stage)) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid case ID or stage']);
        }

        // Validate stage exists
        $valid_stages = getValidWorkflowStages();
        if (!in_array($stage, $valid_stages)) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid stage']);
        }

        try {
            // Get current assignments, timers and work_times
            $stmt = $pdo->prepare("SELECT repair_assignments, stage_timers, work_times FROM transfers WHERE id = ?");
            $stmt->execute([$case_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $assignments = json_decode($row['repair_assignments'] ?: '{}', true);
            if (!is_array($assignments)) {
                $assignments = [];
            }
            
            $timers = json_decode($row['stage_timers'] ?: '{}', true);
            if (!is_array($timers)) {
                $timers = [];
            }

            $workTimes = json_decode($row['work_times'] ?: '{}', true);
            if (!is_array($workTimes)) {
                $workTimes = [];
            }

            $prevTech = $assignments[$stage] ?? null;
            $prevTimer = $timers[$stage] ?? null;
            $nowMs = time() * 1000;
            $userId = getCurrentUserId();

            // Handle assignment change / unassign logic and record work durations for previous technician
            if ($technician_id > 0) {
                // Assign to new technician
                if ($prevTech && $prevTech != $technician_id && $prevTimer) {
                    $elapsed = $nowMs - intval($prevTimer);
                    if (!isset($workTimes[$stage])) $workTimes[$stage] = [];
                    if (!isset($workTimes[$stage][$prevTech])) $workTimes[$stage][$prevTech] = 0;
                    $workTimes[$stage][$prevTech] += $elapsed;
                    // clear old timer
                    unset($timers[$stage]);
                }

                $assignments[$stage] = $technician_id;
                // Start timer if not already running
                if (!isset($timers[$stage])) {
                    $timers[$stage] = $nowMs; // Store as milliseconds for JS compatibility
                }
            } else {
                // Unassign: record work duration if timer exists
                if ($prevTech && $prevTimer) {
                    $elapsed = $nowMs - intval($prevTimer);
                    if (!isset($workTimes[$stage])) $workTimes[$stage] = [];
                    if (!isset($workTimes[$stage][$prevTech])) $workTimes[$stage][$prevTech] = 0;
                    $workTimes[$stage][$prevTech] += $elapsed;
                }
                unset($assignments[$stage]);
                // Stop timer
                unset($timers[$stage]);
            }

            // Persist assignment/timer/workTimes
            $stmt = $pdo->prepare("UPDATE transfers SET repair_assignments = ?, stage_timers = ?, work_times = ? WHERE id = ?");
            $stmt->execute([json_encode($assignments), json_encode($timers), json_encode($workTimes), $case_id]);

            // Append structured assignment change to system_logs and assignment_history for auditing
            $logEntry = json_encode(['type' => 'assignment', 'stage' => $stage, 'from' => $prevTech ?? null, 'to' => $technician_id > 0 ? $technician_id : null, 'by' => $userId ?? null, 'timestamp' => $nowMs]);
            $log_stmt = $pdo->prepare("UPDATE transfers SET system_logs = JSON_ARRAY_APPEND(COALESCE(system_logs, '[]'), '$', CAST(? AS JSON)), assignment_history = JSON_ARRAY_APPEND(COALESCE(assignment_history, '[]'), '$', CAST(? AS JSON)) WHERE id = ?");
            $log_stmt->execute([$logEntry, $logEntry, $case_id]);

            if ($stmt->rowCount() > 0) {
                // Return updated assignments, timers and work times to the client
                $stmt2 = $pdo->prepare("SELECT repair_assignments, stage_timers, stage_statuses, work_times FROM transfers WHERE id = ?");
                $stmt2->execute([$case_id]);
                $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                jsonResponse(['status' => 'success', 'assignments' => json_decode($row2['repair_assignments'] ?: '{}', true), 'timers' => json_decode($row2['stage_timers'] ?: '{}', true), 'statuses' => json_decode($row2['stage_statuses'] ?: '{}', true), 'work_times' => json_decode($row2['work_times'] ?: '{}', true)]);
            } else {
                jsonResponse(['status' => 'error', 'message' => 'Case not found or no changes made']);
            }
        } catch (Exception $e) {
            error_log("Assign technician error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error']);
        }
    }

    if ($action === 'update_urgent' && $method === 'POST') {
        $data = getJsonInput();
        $case_id = intval($data['case_id'] ?? 0);
        $urgent = intval($data['urgent'] ?? 0);

        if ($case_id <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid case ID']);
        }

        try {
            $stmt = $pdo->prepare("UPDATE transfers SET urgent = ? WHERE id = ?");
            $stmt->execute([$urgent, $case_id]);

            if ($stmt->rowCount() > 0) {
                jsonResponse(['status' => 'success']);
            } else {
                jsonResponse(['status' => 'error', 'message' => 'Case not found or no changes made']);
            }
        } catch (Exception $e) {
            error_log("Update urgent error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error']);
        }
    }

    if ($action === 'move_to_next_stage' && $method === 'POST') {
        $data = getJsonInput();
        $case_id = intval($data['case_id'] ?? 0);
        $current_stage = trim($data['stage'] ?? '');

        if ($case_id <= 0 || empty($current_stage)) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid case ID or stage']);
        }

        // Define stage progression
        $stage_progression = getStageProgression();

        if (!isset($stage_progression[$current_stage])) {
            jsonResponse(['status' => 'error', 'message' => 'Cannot advance from this stage']);
        }

        $next_stage = $stage_progression[$current_stage];

        // Validate stages exist
        $valid_stages = getValidWorkflowStages();
        if (!in_array($current_stage, $valid_stages) || !in_array($next_stage, $valid_stages)) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid stage progression']);
        }

        try {
            // Get current data (also fetch work_times)
            $stmt = $pdo->prepare("SELECT repair_stage, repair_assignments, stage_timers, stage_statuses, work_times FROM transfers WHERE id = ?");
            $stmt->execute([$case_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) jsonResponse(['status' => 'error', 'message' => 'Case not found']);

            $assignments = json_decode($row['repair_assignments'] ?: '{}', true);
            if (!is_array($assignments)) $assignments = [];

            $timers = json_decode($row['stage_timers'] ?: '{}', true);
            if (!is_array($timers)) $timers = [];

            $statuses = json_decode($row['stage_statuses'] ?: '{}', true);
            if (!is_array($statuses)) $statuses = [];

            $workTimes = json_decode($row['work_times'] ?: '{}', true);
            if (!is_array($workTimes)) $workTimes = [];

            // If a timer was running on the current stage, record elapsed time for the technician
            $nowMs = time() * 1000;
            if (!empty($timers[$current_stage]) && !empty($assignments[$current_stage])) {
                $prevTech = $assignments[$current_stage];
                $elapsed = $nowMs - intval($timers[$current_stage]);
                if (!isset($workTimes[$current_stage])) $workTimes[$current_stage] = [];
                if (!isset($workTimes[$current_stage][$prevTech])) $workTimes[$current_stage][$prevTech] = 0;
                $workTimes[$current_stage][$prevTech] += $elapsed;

                // Append work_time log
                $logEntry = json_encode(['type' => 'work_time', 'stage' => $current_stage, 'tech' => $prevTech, 'duration_ms' => $elapsed, 'timestamp' => $nowMs]);
                $log_stmt = $pdo->prepare("UPDATE transfers SET system_logs = JSON_ARRAY_APPEND(COALESCE(system_logs, '[]'), '$', CAST(? AS JSON)) WHERE id = ?");
                $log_stmt->execute([$logEntry, $case_id]);
            }

            // Transfer technician assignment to new stage and start timer
            if (isset($assignments[$current_stage])) {
                $technician_id = $assignments[$current_stage];
                $assignments[$next_stage] = $technician_id;
                $timers[$next_stage] = $nowMs; // Start timer in milliseconds
                // Clear assignment from old stage since work is complete
                unset($assignments[$current_stage]);
                unset($timers[$current_stage]);
            }

            // Move case to next stage and update assignments/timers/work_times
            // If moving to 'done', also update status to 'Completed'
            $updateFields = "repair_stage = ?, repair_assignments = ?, stage_timers = ?, work_times = ?";
            $updateValues = [$next_stage, json_encode($assignments), json_encode($timers), json_encode($workTimes)];
            
            if ($next_stage === 'done') {
                $updateFields .= ", status = ?";
                $updateValues[] = 'Completed';
            }
            
            $stmt = $pdo->prepare("UPDATE transfers SET $updateFields WHERE id = ?");
            $updateValues[] = $case_id;
            $stmt->execute($updateValues);

            if ($stmt->rowCount() > 0) {
                // Append a move log
                $moveLog = json_encode(['type' => 'move', 'from' => $current_stage, 'to' => $next_stage, 'tech' => $technician_id ?? null, 'by' => getCurrentUserId(), 'timestamp' => $nowMs]);
                $log_stmt = $pdo->prepare("UPDATE transfers SET system_logs = JSON_ARRAY_APPEND(COALESCE(system_logs, '[]'), '$', CAST(? AS JSON)) WHERE id = ?");
                $log_stmt->execute([$moveLog, $case_id]);

                jsonResponse(['status' => 'success', 'new_stage' => $next_stage, 'assignments' => $assignments, 'timers' => $timers, 'work_times' => $workTimes]);
            } else {
                jsonResponse(['status' => 'error', 'message' => 'Failed to move to next stage']);
            }
        } catch (Exception $e) {
            error_log("Move to next stage error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error']);
        }
    }

    if ($action === 'finish_stage' && $method === 'POST') {
        $data = getJsonInput();
        $case_id = intval($data['case_id'] ?? 0);
        $stage = trim($data['stage'] ?? '');

        if ($case_id <= 0 || empty($stage)) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid case ID or stage']);
        }

        // Validate stage exists
        $valid_stages = getValidWorkflowStages();
        if (!in_array($stage, $valid_stages)) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid stage']);
        }

        // Require technician to be logged in
        $userId = getCurrentUserId();
        if (!$userId) {
            jsonResponse(['status' => 'error', 'message' => 'Unauthorized']);
        }

        try {
            // Get current assignments/statuses/timers and work_times
            $stmt = $pdo->prepare("SELECT repair_assignments, stage_timers, stage_statuses, work_times FROM transfers WHERE id = ?");
            $stmt->execute([$case_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) jsonResponse(['status' => 'error', 'message' => 'Case not found']);

            $assignments = json_decode($row['repair_assignments'] ?: '{}', true);
            if (!is_array($assignments)) $assignments = [];

            $timers = json_decode($row['stage_timers'] ?: '{}', true);
            if (!is_array($timers)) $timers = [];

            $statuses = json_decode($row['stage_statuses'] ?: '{}', true);
            if (!is_array($statuses)) $statuses = [];

            $workTimes = json_decode($row['work_times'] ?: '{}', true);
            if (!is_array($workTimes)) $workTimes = [];

            // Verify the current user is assigned to this stage
            if (empty($assignments[$stage]) || intval($assignments[$stage]) !== intval($userId)) {
                jsonResponse(['status' => 'error', 'message' => 'You are not assigned to this stage']);
            }

            // Mark finished: set status
            $statuses[$stage] = ['status' => 'finished', 'finished_at' => time() * 1000, 'finished_by' => $userId];

            // If a timer was running for this stage and there was an assigned technician, record the elapsed time
            $nowMs = time() * 1000;
            if (!empty($timers[$stage]) && !empty($assignments[$stage])) {
                $techId = $assignments[$stage];
                $elapsed = $nowMs - intval($timers[$stage]);
                if (!isset($workTimes[$stage])) $workTimes[$stage] = [];
                if (!isset($workTimes[$stage][$techId])) $workTimes[$stage][$techId] = 0;
                $workTimes[$stage][$techId] += $elapsed;
                // Append a work time record to system_logs
                $logEntry = json_encode(['type' => 'work_time', 'stage' => $stage, 'tech' => $techId, 'duration_ms' => $elapsed, 'by' => $userId, 'timestamp' => $nowMs]);
                $log_stmt = $pdo->prepare("UPDATE transfers SET system_logs = JSON_ARRAY_APPEND(COALESCE(system_logs, '[]'), '$', CAST(? AS JSON)) WHERE id = ?");
                $log_stmt->execute([$logEntry, $case_id]);
            }

            // Clear the timer for this stage
            unset($timers[$stage]);

            $stmt = $pdo->prepare("UPDATE transfers SET stage_statuses = ?, stage_timers = ?, work_times = ? WHERE id = ?");
            $stmt->execute([json_encode($statuses), json_encode($timers), json_encode($workTimes), $case_id]);

            if ($stmt->rowCount() > 0) {
                // Return updated statuses and timers
                $stmt2 = $pdo->prepare("SELECT repair_assignments, stage_timers, stage_statuses, work_times FROM transfers WHERE id = ?");
                $stmt2->execute([$case_id]);
                $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                jsonResponse(['status' => 'success', 'assignments' => json_decode($row2['repair_assignments'] ?: '{}', true), 'timers' => json_decode($row2['stage_timers'] ?: '{}', true), 'statuses' => json_decode($row2['stage_statuses'] ?: '{}', true), 'work_times' => json_decode($row2['work_times'] ?: '{}', true)]);
            } else {
                jsonResponse(['status' => 'error', 'message' => 'Failed to mark finished']);
            }
        } catch (Exception $e) {
            error_log("Finish stage error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error']);
        }
    }

    // --- CONSUMABLES COSTS ENDPOINTS ---
    
    // Get consumables costs for a month (or all months)
    if ($action === 'get_consumables_costs' && $method === 'GET') {
        $year_month = $_GET['month'] ?? '';
        $technician = $_GET['technician'] ?? '';
        
        try {
            $query = "SELECT * FROM `consumables_costs` WHERE 1=1";
            $params = [];
            
            if ($year_month) {
                $query .= " AND `year_month` = ?";
                $params[] = $year_month;
            }
            if ($technician) {
                $query .= " AND `technician_name` = ?";
                $params[] = $technician;
            }
            
            $query .= " ORDER BY `year_month` DESC, `technician_name` ASC";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $costs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            jsonResponse(['status' => 'success', 'data' => $costs]);
        } catch (Exception $e) {
            error_log("Get consumables costs error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error']);
        }
    }
    
    // Save/Update consumables cost (admin/manager only)
    if ($action === 'save_consumables_cost' && $method === 'POST') {
        // Check admin or manager role
        if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
            http_response_code(403);
            jsonResponse(['status' => 'error', 'message' => 'Access denied. Admin or manager role required.']);
            return;
        }
        
        $data = getJsonInput();
        
        $technician_name = $data['technician_name'] ?? '';
        $year_month = $data['year_month'] ?? '';
        $cost = round(floatval($data['cost'] ?? 0), 2); // Round to 2 decimal places
        $notes = $data['notes'] ?? '';
        
        if (!$technician_name || !$year_month) {
            jsonResponse(['status' => 'error', 'message' => 'Missing technician_name or year_month']);
        }
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO `consumables_costs` (`technician_name`, `year_month`, `cost`, `notes`)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE `cost` = ?, `notes` = ?
            ");
            $stmt->execute([$technician_name, $year_month, $cost, $notes, $cost, $notes]);
            
            jsonResponse(['status' => 'success', 'message' => 'Consumables cost saved']);
        } catch (Exception $e) {
            error_log("Save consumables cost error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error']);
        }
    }
    
    // Delete consumables cost (admin/manager only)
    if ($action === 'delete_consumables_cost' && $method === 'POST') {
        // Check admin or manager role
        if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
            http_response_code(403);
            jsonResponse(['status' => 'error', 'message' => 'Access denied. Admin or manager role required.']);
            return;
        }
        
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) {
            jsonResponse(['status' => 'error', 'message' => 'Missing id']);
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM `consumables_costs` WHERE `id` = ?");
            $stmt->execute([$id]);
            
            jsonResponse(['status' => 'success', 'message' => 'Consumables cost deleted']);
        } catch (Exception $e) {
            error_log("Delete consumables cost error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error']);
        }
    }

    // --- STATUS MANAGEMENT ENDPOINTS ---
    
    // Get all statuses (filterable by type) - public endpoint for dropdowns
    if ($action === 'get_statuses' && $method === 'GET') {
        $type = $_GET['type'] ?? ''; // 'case', 'repair', or empty for all
        $active_only = isset($_GET['active_only']) ? (bool)$_GET['active_only'] : true;
        
        try {
            // Check if table exists first
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'statuses'");
            if ($tableCheck->rowCount() === 0) {
                jsonResponse(['status' => 'success', 'data' => []]);
            }
            
            $query = "SELECT * FROM `statuses` WHERE 1=1";
            $params = [];
            
            if ($type) {
                $query .= " AND `type` = ?";
                $params[] = $type;
            }
            if ($active_only) {
                $query .= " AND `is_active` = 1";
            }
            
            $query .= " ORDER BY `type`, `sort_order` ASC";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            jsonResponse(['status' => 'success', 'data' => $statuses]);
        } catch (Exception $e) {
            error_log("Get statuses error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error']);
        }
    }
    
    // Get single status by ID
    if ($action === 'get_status' && $method === 'GET') {
        $id = intval($_GET['id'] ?? 0);
        
        if (!$id) {
            jsonResponse(['status' => 'error', 'message' => 'Missing status ID']);
        }
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM `statuses` WHERE `id` = ?");
            $stmt->execute([$id]);
            $status = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($status) {
                jsonResponse(['status' => 'success', 'data' => $status]);
            } else {
                jsonResponse(['status' => 'error', 'message' => 'Status not found']);
            }
        } catch (Exception $e) {
            error_log("Get status error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error']);
        }
    }
    
    // Save/Update status (admin only)
    if ($action === 'save_status' && $method === 'POST') {
        // Check admin role
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            jsonResponse(['status' => 'error', 'message' => 'Access denied. Admin role required.']);
        }
        
        $data = getJsonInput();
        
        $id = intval($data['id'] ?? 0);
        $type = $data['type'] ?? '';
        $name = trim($data['name'] ?? '');
        $color = $data['color'] ?? '#6B7280';
        $bg_color = $data['bg_color'] ?? '#F3F4F6';
        $icon = $data['icon'] ?? null;
        $sort_order = intval($data['sort_order'] ?? 0);
        $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;
        
        if (!$type || !$name) {
            jsonResponse(['status' => 'error', 'message' => 'Missing type or name']);
        }
        
        if (!in_array($type, ['case', 'repair'])) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid type. Must be "case" or "repair"']);
        }
        
        try {
            if ($id > 0) {
                // Update existing
                $stmt = $pdo->prepare("
                    UPDATE `statuses` 
                    SET `type` = ?, `name` = ?, `color` = ?, `bg_color` = ?, `icon` = ?, `sort_order` = ?, `is_active` = ?
                    WHERE `id` = ?
                ");
                $stmt->execute([$type, $name, $color, $bg_color, $icon, $sort_order, $is_active, $id]);
                jsonResponse(['status' => 'success', 'message' => 'Status updated', 'id' => $id]);
            } else {
                // Insert new - get max sort_order for this type
                $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(`sort_order`), 0) + 1 FROM `statuses` WHERE `type` = ?");
                $maxStmt->execute([$type]);
                $newOrder = $maxStmt->fetchColumn();
                
                $stmt = $pdo->prepare("
                    INSERT INTO `statuses` (`type`, `name`, `color`, `bg_color`, `icon`, `sort_order`, `is_active`)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$type, $name, $color, $bg_color, $icon, $sort_order ?: $newOrder, $is_active]);
                $newId = $pdo->lastInsertId();
                jsonResponse(['status' => 'success', 'message' => 'Status created', 'id' => $newId]);
            }
        } catch (Exception $e) {
            error_log('Status save error: ' . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Failed to save status']);
        }
    }
    
    // Delete status (admin only)
    if ($action === 'delete_status' && $method === 'POST') {
        // Check admin role
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            jsonResponse(['status' => 'error', 'message' => 'Access denied. Admin role required.']);
        }
        
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) {
            jsonResponse(['status' => 'error', 'message' => 'Missing status ID']);
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM `statuses` WHERE `id` = ?");
            $stmt->execute([$id]);
            
            jsonResponse(['status' => 'success', 'message' => 'Status deleted']);
        } catch (Exception $e) {
            error_log('Status delete error: ' . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Failed to delete status']);
        }
    }
    
    // Reorder statuses (admin only)
    if ($action === 'reorder_statuses' && $method === 'POST') {
        // Check admin role
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            jsonResponse(['status' => 'error', 'message' => 'Access denied. Admin role required.']);
        }
        
        $data = getJsonInput();
        $orders = $data['orders'] ?? []; // Array of {id: X, sort_order: Y}
        
        if (empty($orders)) {
            jsonResponse(['status' => 'error', 'message' => 'Missing orders array']);
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE `statuses` SET `sort_order` = ? WHERE `id` = ?");
            foreach ($orders as $order) {
                $stmt->execute([intval($order['sort_order']), intval($order['id'])]);
            }
            
            jsonResponse(['status' => 'success', 'message' => 'Statuses reordered']);
        } catch (Exception $e) {
            error_log('Statuses reorder error: ' . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Failed to reorder statuses']);
        }
    }
    
    // Toggle status active/inactive (admin only)
    if ($action === 'toggle_status' && $method === 'POST') {
        // Check admin role
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            jsonResponse(['status' => 'error', 'message' => 'Access denied. Admin role required.']);
        }
        
        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        
        if (!$id) {
            jsonResponse(['status' => 'error', 'message' => 'Missing status ID']);
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE `statuses` SET `is_active` = NOT `is_active` WHERE `id` = ?");
            $stmt->execute([$id]);
            
            // Get the new state
            $stmt2 = $pdo->prepare("SELECT `is_active` FROM `statuses` WHERE `id` = ?");
            $stmt2->execute([$id]);
            $newState = $stmt2->fetchColumn();
            
            jsonResponse(['status' => 'success', 'message' => 'Status toggled', 'is_active' => (bool)$newState]);
        } catch (Exception $e) {
            error_log('Status toggle error: ' . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Failed to toggle status']);
        }
    }

    // ============ UPLOAD CASE IMAGE TO FIREBASE STORAGE ============
    if ($action === 'upload_case_image' && $method === 'POST') {
        // Check authentication
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            jsonResponse(['status' => 'error', 'message' => 'Unauthorized']);
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds server size limit',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form size limit',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            ];
            $errorCode = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
            jsonResponse(['status' => 'error', 'message' => $errorMessages[$errorCode] ?? 'Upload error']);
        }
        
        $file = $_FILES['image'];
        $caseId = intval($_POST['case_id'] ?? 0);
        
        if (!$caseId) {
            jsonResponse(['status' => 'error', 'message' => 'Missing case ID']);
        }
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid file type. Allowed: JPG, PNG, WEBP, GIF']);
        }
        
        // Validate file size (10MB max)
        if ($file['size'] > 10 * 1024 * 1024) {
            jsonResponse(['status' => 'error', 'message' => 'File too large. Maximum size: 10MB']);
        }
        
        // Generate unique filename
        $timestamp = time();
        $randomStr = bin2hex(random_bytes(4));
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $filename = "cases/{$caseId}/{$timestamp}_{$randomStr}.{$ext}";
        
        // Read file contents
        $fileContents = file_get_contents($file['tmp_name']);
        if ($fileContents === false) {
            jsonResponse(['status' => 'error', 'message' => 'Failed to read uploaded file']);
        }
        
        // Get Firebase Storage access token
        $keyFile = __DIR__ . '/service-account.json';
        if (!file_exists($keyFile)) {
            jsonResponse(['status' => 'error', 'message' => 'Firebase service account not configured']);
        }
        
        $keyData = json_decode(file_get_contents($keyFile), true);
        $projectId = $keyData['project_id'];
        // Firebase Storage bucket name
        $storageBucket = $projectId . '.firebasestorage.app';
        
        // Get access token using the helper function with storage scope (with detailed error info)
        $authResult = getAccessTokenWithScope($keyFile, 'https://www.googleapis.com/auth/devstorage.full_control', true);
        
        if (!isset($authResult['token'])) {
            error_log("Firebase Storage token error: " . json_encode($authResult));
            jsonResponse([
                'status' => 'error', 
                'message' => 'Failed to authenticate with Firebase Storage',
                'debug' => $authResult
            ]);
        }
        
        $accessToken = $authResult['token'];
        
        // Upload to Firebase Storage
        $uploadUrl = "https://storage.googleapis.com/upload/storage/v1/b/{$storageBucket}/o?uploadType=media&name=" . urlencode($filename);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uploadUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContents);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: ' . $mimeType,
            'Content-Length: ' . strlen($fileContents)
        ]);
        
        $uploadResponse = curl_exec($ch);
        $uploadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($uploadHttpCode !== 200) {
            error_log("Firebase Storage upload failed. HTTP: {$uploadHttpCode}, Error: {$curlError}, Response: {$uploadResponse}");
            jsonResponse(['status' => 'error', 'message' => 'Failed to upload to Firebase Storage', 'debug' => $uploadResponse]);
        }
        
        $uploadData = json_decode($uploadResponse, true);
        
        // Construct the public download URL
        $downloadUrl = "https://firebasestorage.googleapis.com/v0/b/{$storageBucket}/o/" . urlencode($filename) . "?alt=media";
        
        // Optionally make the file public (set metadata)
        $metadataUrl = "https://storage.googleapis.com/storage/v1/b/{$storageBucket}/o/" . urlencode($filename);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $metadataUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'metadata' => [
                'caseId' => (string)$caseId,
                'uploadedBy' => $_SESSION['user_id'] ?? 'unknown',
                'uploadedAt' => date('c')
            ]
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_exec($ch);
        curl_close($ch);
        
        jsonResponse([
            'status' => 'success',
            'message' => 'Image uploaded successfully',
            'url' => $downloadUrl,
            'filename' => $filename
        ]);
    }

    // ============ DELETE CASE IMAGE FROM FIREBASE STORAGE ============
    if ($action === 'delete_case_image' && $method === 'POST') {
        // Check authentication
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            jsonResponse(['status' => 'error', 'message' => 'Unauthorized']);
        }
        
        $data = getJsonInput();
        $imageUrl = $data['url'] ?? '';
        
        if (empty($imageUrl)) {
            jsonResponse(['status' => 'error', 'message' => 'Missing image URL']);
        }
        
        // Extract the filename from the URL
        // URL format: https://firebasestorage.googleapis.com/v0/b/{bucket}/o/{encodedFilename}?alt=media
        if (preg_match('/\/o\/([^?]+)/', $imageUrl, $matches)) {
            $encodedFilename = $matches[1];
            $filename = urldecode($encodedFilename);
        } else {
            jsonResponse(['status' => 'error', 'message' => 'Invalid image URL format']);
        }
        
        // Get Firebase Storage access token
        $keyFile = __DIR__ . '/service-account.json';
        if (!file_exists($keyFile)) {
            jsonResponse(['status' => 'error', 'message' => 'Firebase service account not configured']);
        }
        
        $keyData = json_decode(file_get_contents($keyFile), true);
        $projectId = $keyData['project_id'];
        $storageBucket = $projectId . '.firebasestorage.app';
        
        // Get access token using the helper function
        $accessToken = getAccessTokenWithScope($keyFile, 'https://www.googleapis.com/auth/devstorage.full_control');
        
        if (!$accessToken) {
            jsonResponse(['status' => 'error', 'message' => 'Failed to authenticate with Firebase']);
        }
        
        // Delete from Firebase Storage
        $deleteUrl = "https://storage.googleapis.com/storage/v1/b/{$storageBucket}/o/" . urlencode($filename);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $deleteUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);
        
        $deleteResponse = curl_exec($ch);
        $deleteHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // 204 = success, 404 = already deleted (treat as success)
        if ($deleteHttpCode === 204 || $deleteHttpCode === 404) {
            jsonResponse(['status' => 'success', 'message' => 'Image deleted successfully']);
        } else {
            error_log("Firebase Storage delete failed. HTTP: {$deleteHttpCode}, Response: {$deleteResponse}");
            jsonResponse(['status' => 'error', 'message' => 'Failed to delete from Firebase Storage']);
        }
    }

    // =============================================
    // --- OFFERS SYSTEM ENDPOINTS ---
    // =============================================

    // GET ALL OFFERS (Manager — auth required)
    if ($action === 'get_offers' && $method === 'GET') {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            jsonResponse(['error' => 'Unauthorized']);
        }

        // Auto-expire offers past their valid_until date
        $pdo->exec("UPDATE offers SET status = 'expired' WHERE status = 'active' AND valid_until < NOW()");

        $stmt = $pdo->query("
            SELECT o.*, 
                   u.full_name AS created_by_name,
                   (SELECT COUNT(*) FROM offer_redemptions WHERE offer_id = o.id) AS redemption_count
            FROM offers o
            LEFT JOIN users u ON o.created_by = u.id
            ORDER BY o.created_at DESC
        ");
        $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(['status' => 'success', 'offers' => $offers]);
    }

    // CREATE OFFER (Manager — auth required)
    if ($action === 'create_offer' && $method === 'POST') {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            jsonResponse(['error' => 'Unauthorized']);
        }

        $data = getJsonInput();
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $discount_type = $data['discount_type'] ?? 'percentage';
        $discount_value = floatval($data['discount_value'] ?? 0);
        $min_order_amount = isset($data['min_order_amount']) && $data['min_order_amount'] !== '' ? floatval($data['min_order_amount']) : null;
        $valid_from = $data['valid_from'] ?? date('Y-m-d H:i:s');
        $valid_until = $data['valid_until'] ?? '';
        $max_redemptions = isset($data['max_redemptions']) && $data['max_redemptions'] !== '' ? intval($data['max_redemptions']) : null;
        $target_phone = trim($data['target_phone'] ?? '') ?: null;
        $target_name = trim($data['target_name'] ?? '') ?: null;

        if (empty($title)) {
            jsonResponse(['status' => 'error', 'message' => 'Offer title is required']);
        }
        if (empty($valid_until)) {
            jsonResponse(['status' => 'error', 'message' => 'Expiry date is required']);
        }
        if (!in_array($discount_type, ['percentage', 'fixed', 'free_service'])) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid discount type']);
        }
        if ($discount_type === 'percentage' && ($discount_value <= 0 || $discount_value > 100)) {
            jsonResponse(['status' => 'error', 'message' => 'Percentage must be between 1 and 100']);
        }
        if ($discount_type === 'fixed' && $discount_value <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Fixed discount must be greater than 0']);
        }

        // Generate unique 8-char alphanumeric code
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        // Ensure uniqueness
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM offers WHERE code = ?");
        $checkStmt->execute([$code]);
        while ($checkStmt->fetchColumn() > 0) {
            $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $checkStmt->execute([$code]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO offers (code, title, description, discount_type, discount_value, min_order_amount, valid_from, valid_until, max_redemptions, target_phone, target_name, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $code, $title, $description, $discount_type, $discount_value,
            $min_order_amount, $valid_from, $valid_until, $max_redemptions,
            $target_phone, $target_name, $_SESSION['user_id']
        ]);

        jsonResponse(['status' => 'success', 'message' => 'Offer created', 'offer_id' => $pdo->lastInsertId(), 'code' => $code]);
    }

    // UPDATE OFFER (Manager — auth required)
    if ($action === 'update_offer' && $method === 'POST') {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            jsonResponse(['error' => 'Unauthorized']);
        }

        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid offer ID']);
        }

        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $discount_type = $data['discount_type'] ?? 'percentage';
        $discount_value = floatval($data['discount_value'] ?? 0);
        $min_order_amount = isset($data['min_order_amount']) && $data['min_order_amount'] !== '' ? floatval($data['min_order_amount']) : null;
        $valid_from = $data['valid_from'] ?? '';
        $valid_until = $data['valid_until'] ?? '';
        $max_redemptions = isset($data['max_redemptions']) && $data['max_redemptions'] !== '' ? intval($data['max_redemptions']) : null;
        $target_phone = trim($data['target_phone'] ?? '') ?: null;
        $target_name = trim($data['target_name'] ?? '') ?: null;

        if (empty($title) || empty($valid_until)) {
            jsonResponse(['status' => 'error', 'message' => 'Title and expiry date are required']);
        }

        $stmt = $pdo->prepare("
            UPDATE offers SET title = ?, description = ?, discount_type = ?, discount_value = ?, 
                   min_order_amount = ?, valid_from = ?, valid_until = ?, max_redemptions = ?,
                   target_phone = ?, target_name = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $title, $description, $discount_type, $discount_value,
            $min_order_amount, $valid_from, $valid_until, $max_redemptions,
            $target_phone, $target_name, $id
        ]);

        jsonResponse(['status' => 'success', 'message' => 'Offer updated']);
    }

    // TOGGLE OFFER STATUS (pause/activate)
    if ($action === 'toggle_offer_status' && $method === 'POST') {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            jsonResponse(['error' => 'Unauthorized']);
        }

        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        $new_status = $data['status'] ?? '';

        if ($id <= 0 || !in_array($new_status, ['active', 'paused', 'expired'])) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid parameters']);
        }

        $stmt = $pdo->prepare("UPDATE offers SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $id]);
        jsonResponse(['status' => 'success', 'message' => 'Offer status updated']);
    }

    // DELETE OFFER
    if ($action === 'delete_offer' && $method === 'POST') {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            jsonResponse(['error' => 'Unauthorized']);
        }

        $data = getJsonInput();
        $id = intval($data['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid offer ID']);
        }

        $stmt = $pdo->prepare("DELETE FROM offers WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['status' => 'success', 'message' => 'Offer deleted']);
    }

    // SEND OFFER SMS
    if ($action === 'send_offer_sms' && $method === 'POST') {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            jsonResponse(['error' => 'Unauthorized']);
        }

        $data = getJsonInput();
        $offer_id = intval($data['offer_id'] ?? 0);
        $phone = trim($data['phone'] ?? '');

        if ($offer_id <= 0 || empty($phone)) {
            jsonResponse(['status' => 'error', 'message' => 'Offer ID and phone number are required']);
        }

        // Clean phone number
        $to = preg_replace('/\D/', '', $phone);
        if (!preg_match('/^995/', $to)) {
            $to = '995' . $to;
        }
        if (strlen($to) < 11) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid phone number']);
        }

        // Fetch offer
        $stmt = $pdo->prepare("SELECT * FROM offers WHERE id = ? AND status = 'active'");
        $stmt->execute([$offer_id]);
        $offer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$offer) {
            jsonResponse(['status' => 'error', 'message' => 'Offer not found or not active']);
        }

        // Build discount text
        $discountText = '';
        if ($offer['discount_type'] === 'percentage') {
            $discountText = intval($offer['discount_value']) . '% ფასდაკლება';
        } elseif ($offer['discount_type'] === 'fixed') {
            $discountText = number_format($offer['discount_value'], 0) . '₾ ფასდაკლება';
        } else {
            $discountText = 'უფასო სერვისი';
        }

        $name = $offer['target_name'] ?: 'მომხმარებელო';

        // Ensure tracking slugs table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `offer_tracking_slugs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `offer_id` INT NOT NULL,
            `phone` VARCHAR(20) NOT NULL,
            `slug` VARCHAR(16) NOT NULL UNIQUE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_offer_phone` (`offer_id`, `phone`),
            INDEX `idx_slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Generate unique tracking slug for this phone
        $trackingSlug = substr(bin2hex(random_bytes(6)), 0, 12);
        $slugStmt = $pdo->prepare("INSERT INTO offer_tracking_slugs (offer_id, phone, slug) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE slug = VALUES(slug), created_at = NOW()");
        $slugStmt->execute([$offer_id, $phone, $trackingSlug]);

        $link = "https://portal.otoexpress.ge/redeem_offer.php?code=" . $offer['code'] . "&t=" . $trackingSlug;

        $smsText = "გამარჯობა, როგორც ჩვენი კომპანიის მომხმარებელს, კომპანიისგან გადმოგეცათ ვაუჩერი. ვაუჩერის დეტალების სანახავად მიყევით ბმულს: {$link}";

        try {
            $api_key = defined('SMS_API_KEY') ? SMS_API_KEY : "5c88b0316e44d076d4677a4860959ef71ce049ce704b559355568a362f40ade1";
            $url = "https://api.gosms.ge/api/sendsms?api_key=$api_key&to=$to&from=OTOMOTORS&text=" . urlencode($smsText);

            $context = stream_context_create(['http' => ['timeout' => 10, 'user_agent' => 'OTOMOTORS Portal']]);
            $response = @file_get_contents($url, false, $context);

            // Update offer record
            $pdo->prepare("UPDATE offers SET sms_sent_at = NOW(), target_phone = ? WHERE id = ?")->execute([$phone, $offer_id]);

            if ($response !== false) {
                jsonResponse(['status' => 'success', 'message' => 'SMS sent successfully']);
            } else {
                jsonResponse(['status' => 'error', 'message' => 'SMS sending failed — network error']);
            }
        } catch (Exception $e) {
            error_log("Offer SMS error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'SMS sending failed']);
        }
    }

    // GET CUSTOMERS FOR BULK SMS (Manager — auth required)
    if ($action === 'get_customers_for_bulk_sms' && $method === 'GET') {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            jsonResponse(['error' => 'Unauthorized']);
        }

        $status_id = $_GET['status_id'] ?? '';

        try {
            $query = "SELECT DISTINCT t.phone, t.name, t.status, s.name as status_name 
                      FROM transfers t 
                      LEFT JOIN statuses s ON t.status_id = s.id
                      WHERE t.phone IS NOT NULL AND t.phone != '' AND LENGTH(t.phone) >= 9";
            $params = [];

            if ($status_id === 'all_active') {
                // All active cases (not completed status)
                $query .= " AND (t.status NOT IN ('Completed', 'Done', 'Issue') OR t.status IS NULL)";
            } elseif (is_numeric($status_id) && intval($status_id) > 0) {
                // Specific status
                $query .= " AND t.status_id = ?";
                $params[] = intval($status_id);
            }

            $query .= " ORDER BY t.name ASC";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Deduplicate by phone number
            $unique = [];
            foreach ($customers as $c) {
                $phone = preg_replace('/[^0-9]/', '', $c['phone']);
                if (!isset($unique[$phone])) {
                    $unique[$phone] = $c;
                    $unique[$phone]['phone'] = $phone;
                }
            }

            jsonResponse(['status' => 'success', 'customers' => array_values($unique)]);
        } catch (Exception $e) {
            error_log("Get customers error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Failed to load customers']);
        }
    }

    // BULK SEND OFFER SMS (Manager — auth required)
    if ($action === 'bulk_send_offer_sms' && $method === 'POST') {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            jsonResponse(['error' => 'Unauthorized']);
        }

        $data = getJsonInput();
        $offer_id = intval($data['offer_id'] ?? 0);
        $phones = $data['phones'] ?? [];

        if ($offer_id <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid offer ID']);
        }
        if (empty($phones) || !is_array($phones)) {
            jsonResponse(['status' => 'error', 'message' => 'No phone numbers provided']);
        }

        // Get offer
        $stmt = $pdo->prepare("SELECT * FROM offers WHERE id = ? AND status = 'active'");
        $stmt->execute([$offer_id]);
        $offer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$offer) {
            jsonResponse(['status' => 'error', 'message' => 'Offer not found or not active']);
        }

        // Build discount text
        $discountText = '';
        if ($offer['discount_type'] === 'percentage') {
            $discountText = intval($offer['discount_value']) . '% ფასდაკლება';
        } elseif ($offer['discount_type'] === 'fixed') {
            $discountText = number_format($offer['discount_value'], 0) . '₾ ფასდაკლება';
        } else {
            $discountText = 'უფასო სერვისი';
        }

        $baseLink = "https://portal.otoexpress.ge/redeem_offer.php?code=" . $offer['code'];
        $api_key = defined('SMS_API_KEY') ? SMS_API_KEY : "5c88b0316e44d076d4677a4860959ef71ce049ce704b559355568a362f40ade1";

        // Ensure tracking slugs table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `offer_tracking_slugs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `offer_id` INT NOT NULL,
            `phone` VARCHAR(20) NOT NULL,
            `slug` VARCHAR(16) NOT NULL UNIQUE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_offer_phone` (`offer_id`, `phone`),
            INDEX `idx_slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $sent_count = 0;
        $failed_count = 0;

        foreach ($phones as $phone) {
            $phone = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($phone) < 9) continue;

            // Lookup customer name
            $nameStmt = $pdo->prepare("SELECT name FROM transfers WHERE phone = ? ORDER BY id DESC LIMIT 1");
            $nameStmt->execute([$phone]);
            $customerName = $nameStmt->fetchColumn() ?: 'მომხმარებელო';

            // Generate unique tracking slug for this phone
            $trackingSlug = substr(bin2hex(random_bytes(6)), 0, 12);
            $slugStmt = $pdo->prepare("INSERT INTO offer_tracking_slugs (offer_id, phone, slug) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE slug = VALUES(slug), created_at = NOW()");
            $slugStmt->execute([$offer_id, $phone, $trackingSlug]);

            $link = $baseLink . "&t=" . $trackingSlug;
            $smsText = "გამარჯობა, როგორც ჩვენი კომპანიის მომხმარებელს, კომპანიისგან გადმოგეცათ ვაუჩერი. ვაუჩერის დეტალების სანახავად მიყევით ბმულს: {$link}";

            try {
                $url = "https://api.gosms.ge/api/sendsms?api_key=$api_key&to=$phone&from=OTOMOTORS&text=" . urlencode($smsText);
                $context = stream_context_create(['http' => ['timeout' => 5, 'user_agent' => 'OTOMOTORS Portal']]);
                $response = @file_get_contents($url, false, $context);

                if ($response !== false) {
                    $sent_count++;
                } else {
                    $failed_count++;
                }

                // Small delay to not overwhelm SMS API
                usleep(100000); // 100ms
            } catch (Exception $e) {
                $failed_count++;
                error_log("Bulk SMS error for $phone: " . $e->getMessage());
            }
        }

        // Update offer record
        if ($sent_count > 0) {
            $pdo->prepare("UPDATE offers SET sms_sent_at = NOW() WHERE id = ?")->execute([$offer_id]);
        }

        jsonResponse([
            'status' => 'success',
            'message' => "Sent to $sent_count customers" . ($failed_count > 0 ? ", $failed_count failed" : ""),
            'sent_count' => $sent_count,
            'failed_count' => $failed_count
        ]);
    }

    // TRACK OFFER VIEW (Public — no auth)
    if ($action === 'track_offer_view' && $method === 'POST') {
        $data = getJsonInput();
        $offer_id = intval($data['offer_id'] ?? 0);
        $trackingSlug = trim($data['tracking_slug'] ?? '');

        if ($offer_id <= 0) {
            jsonResponse(['status' => 'error']);
        }

        try {
            // Ensure table exists with phone column
            $pdo->exec("CREATE TABLE IF NOT EXISTS `offer_views` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `offer_id` INT NOT NULL,
                `phone` VARCHAR(20) DEFAULT NULL,
                `viewed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `ip_address` VARCHAR(45) DEFAULT NULL,
                `user_agent` VARCHAR(500) DEFAULT NULL,
                INDEX `idx_offer_id` (`offer_id`),
                INDEX `idx_viewed_at` (`viewed_at`),
                INDEX `idx_phone` (`phone`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Add phone column if missing (for existing tables)
            try {
                $pdo->exec("ALTER TABLE offer_views ADD COLUMN `phone` VARCHAR(20) DEFAULT NULL AFTER `offer_id`");
                $pdo->exec("ALTER TABLE offer_views ADD INDEX `idx_phone` (`phone`)");
            } catch (Exception $e) { /* column exists */ }

            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

            // Lookup phone from tracking slug
            $phone = null;
            if (!empty($trackingSlug)) {
                $slugStmt = $pdo->prepare("SELECT phone FROM offer_tracking_slugs WHERE slug = ? AND offer_id = ?");
                $slugStmt->execute([$trackingSlug, $offer_id]);
                $phone = $slugStmt->fetchColumn() ?: null;
            }

            $stmt = $pdo->prepare("INSERT INTO offer_views (offer_id, phone, ip_address, user_agent) VALUES (?, ?, ?, ?)");
            $stmt->execute([$offer_id, $phone, $ip, $ua]);

            jsonResponse(['status' => 'success']);
        } catch (Exception $e) {
            error_log("Track offer view error: " . $e->getMessage());
            jsonResponse(['status' => 'error']);
        }
    }

    // GET OFFER VIEWS (Manager — auth required)
    if ($action === 'get_offer_views' && $method === 'GET') {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            jsonResponse(['error' => 'Unauthorized']);
        }

        $offer_id = intval($_GET['offer_id'] ?? 0);
        if ($offer_id <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid offer ID']);
        }

        try {
            // Check if table exists
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'offer_views'");
            if ($tableCheck->rowCount() === 0) {
                jsonResponse(['status' => 'success', 'views' => [], 'total' => 0, 'unique' => 0]);
            }

            $stmt = $pdo->prepare("SELECT * FROM offer_views WHERE offer_id = ? ORDER BY viewed_at DESC LIMIT 100");
            $stmt->execute([$offer_id]);
            $views = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Lookup customer names for views with phone numbers
            foreach ($views as &$view) {
                if (!empty($view['phone'])) {
                    $nameStmt = $pdo->prepare("SELECT name FROM transfers WHERE phone = ? ORDER BY id DESC LIMIT 1");
                    $nameStmt->execute([$view['phone']]);
                    $view['customer_name'] = $nameStmt->fetchColumn() ?: null;
                } else {
                    $view['customer_name'] = null;
                }
            }
            unset($view);

            // Get counts - unique by phone if available, otherwise by IP
            $countStmt = $pdo->prepare("SELECT COUNT(*) as total, COUNT(DISTINCT COALESCE(phone, ip_address)) as unique_views FROM offer_views WHERE offer_id = ?");
            $countStmt->execute([$offer_id]);
            $counts = $countStmt->fetch(PDO::FETCH_ASSOC);

            jsonResponse([
                'status' => 'success',
                'views' => $views,
                'total' => intval($counts['total']),
                'unique' => intval($counts['unique_views'])
            ]);
        } catch (Exception $e) {
            error_log("Get offer views error: " . $e->getMessage());
            jsonResponse(['status' => 'success', 'views' => [], 'total' => 0, 'unique' => 0]);
        }
    }

    // GET PUBLIC OFFER (No auth — public customer page)
    if ($action === 'get_public_offer' && $method === 'GET') {
        $code = strtoupper(trim($_GET['code'] ?? ''));
        $trackingSlug = trim($_GET['t'] ?? '');

        if (empty($code) || !preg_match('/^[A-Z0-9]{6,12}$/', $code)) {
            http_response_code(400);
            jsonResponse(['error' => 'Invalid offer code']);
        }

        // Auto-expire
        $pdo->exec("UPDATE offers SET status = 'expired' WHERE status = 'active' AND valid_until < NOW()");

        $stmt = $pdo->prepare("SELECT id, code, title, description, discount_type, discount_value, min_order_amount, valid_from, valid_until, max_redemptions, times_redeemed, status, target_name FROM offers WHERE code = ?");
        $stmt->execute([$code]);
        $offer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$offer) {
            http_response_code(404);
            jsonResponse(['error' => 'Offer not found']);
        }

        // Check if max redemptions reached
        if ($offer['max_redemptions'] !== null && $offer['times_redeemed'] >= $offer['max_redemptions']) {
            $offer['is_exhausted'] = true;
        } else {
            $offer['is_exhausted'] = false;
        }

        // Check if this specific customer (via tracking slug) has already redeemed
        $offer['is_redeemed_by_viewer'] = false;
        if (!empty($trackingSlug)) {
            try {
                // Lookup phone from tracking slug
                $slugStmt = $pdo->prepare("SELECT phone FROM offer_tracking_slugs WHERE slug = ? AND offer_id = ?");
                $slugStmt->execute([$trackingSlug, $offer['id']]);
                $viewerPhone = $slugStmt->fetchColumn();
                
                if ($viewerPhone) {
                    // Check if this phone has redeemed the offer
                    $redeemCheck = $pdo->prepare("SELECT COUNT(*) FROM offer_redemptions WHERE offer_id = ? AND customer_phone = ?");
                    $redeemCheck->execute([$offer['id'], $viewerPhone]);
                    if ($redeemCheck->fetchColumn() > 0) {
                        $offer['is_redeemed_by_viewer'] = true;
                    }
                }
            } catch (Exception $e) {
                // Ignore errors, just don't show redeemed status
            }
        }

        jsonResponse($offer);
    }

    // REDEEM OFFER (Public — no auth)
    if ($action === 'redeem_offer' && $method === 'POST') {
        $data = getJsonInput();
        $code = strtoupper(trim($data['code'] ?? ''));
        $customer_name = trim($data['customer_name'] ?? '');
        $customer_phone = trim($data['customer_phone'] ?? '');

        if (empty($code)) {
            jsonResponse(['status' => 'error', 'message' => 'Offer code is required']);
        }
        if (empty($customer_name) || empty($customer_phone)) {
            jsonResponse(['status' => 'error', 'message' => 'Name and phone number are required']);
        }

        // Auto-expire
        $pdo->exec("UPDATE offers SET status = 'expired' WHERE status = 'active' AND valid_until < NOW()");

        $stmt = $pdo->prepare("SELECT * FROM offers WHERE code = ?");
        $stmt->execute([$code]);
        $offer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$offer) {
            jsonResponse(['status' => 'error', 'message' => 'Offer not found']);
        }
        if ($offer['status'] !== 'active') {
            jsonResponse(['status' => 'error', 'message' => 'This offer is no longer active']);
        }
        if ($offer['max_redemptions'] !== null && $offer['times_redeemed'] >= $offer['max_redemptions']) {
            jsonResponse(['status' => 'error', 'message' => 'This offer has been fully redeemed']);
        }

        // Check duplicate redemption by phone
        $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM offer_redemptions WHERE offer_id = ? AND customer_phone = ?");
        $dupStmt->execute([$offer['id'], $customer_phone]);
        if ($dupStmt->fetchColumn() > 0) {
            jsonResponse(['status' => 'error', 'message' => 'You have already redeemed this offer']);
        }

        // Record redemption
        $pdo->prepare("INSERT INTO offer_redemptions (offer_id, customer_name, customer_phone) VALUES (?, ?, ?)")
            ->execute([$offer['id'], $customer_name, $customer_phone]);

        // Increment counter
        $pdo->prepare("UPDATE offers SET times_redeemed = times_redeemed + 1 WHERE id = ?")->execute([$offer['id']]);

        jsonResponse(['status' => 'success', 'message' => 'Offer redeemed successfully!']);
    }

    // ADMIN REDEEM OFFER (Manager — auth required, with logging)
    if ($action === 'admin_redeem_offer' && $method === 'POST') {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            jsonResponse(['error' => 'Unauthorized']);
        }

        $data = getJsonInput();
        $offer_id = intval($data['offer_id'] ?? 0);
        $customer_phone = trim($data['customer_phone'] ?? '');
        $notes = trim($data['notes'] ?? '');

        if ($offer_id <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid offer ID']);
        }
        if (empty($customer_phone)) {
            jsonResponse(['status' => 'error', 'message' => 'Phone number is required']);
        }

        // Lookup customer name from transfers table by phone
        $customerStmt = $pdo->prepare("SELECT name FROM transfers WHERE phone = ? ORDER BY id DESC LIMIT 1");
        $customerStmt->execute([$customer_phone]);
        $customer_name = $customerStmt->fetchColumn() ?: 'Customer';

        // Auto-expire old offers
        $pdo->exec("UPDATE offers SET status = 'expired' WHERE status = 'active' AND valid_until < NOW()");

        $stmt = $pdo->prepare("SELECT * FROM offers WHERE id = ?");
        $stmt->execute([$offer_id]);
        $offer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$offer) {
            jsonResponse(['status' => 'error', 'message' => 'Offer not found']);
        }
        if ($offer['status'] !== 'active') {
            jsonResponse(['status' => 'error', 'message' => 'This offer is not active']);
        }
        if ($offer['max_redemptions'] !== null && $offer['times_redeemed'] >= $offer['max_redemptions']) {
            jsonResponse(['status' => 'error', 'message' => 'This offer has reached max redemptions']);
        }

        // Check duplicate redemption by phone for this offer
        $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM offer_redemptions WHERE offer_id = ? AND customer_phone = ?");
        $dupStmt->execute([$offer_id, $customer_phone]);
        if ($dupStmt->fetchColumn() > 0) {
            jsonResponse(['status' => 'error', 'message' => 'This phone number has already redeemed this offer']);
        }

        // Ensure redeemed_by column exists (migration-safe)
        try {
            $pdo->exec("ALTER TABLE offer_redemptions ADD COLUMN redeemed_by INT DEFAULT NULL AFTER notes");
        } catch (Exception $e) {
            // Column likely already exists
        }

        // Record redemption with operator ID
        $pdo->prepare("INSERT INTO offer_redemptions (offer_id, customer_name, customer_phone, notes, redeemed_by) VALUES (?, ?, ?, ?, ?)")
            ->execute([$offer_id, $customer_name, $customer_phone, $notes ?: null, $_SESSION['user_id']]);

        // Increment counter
        $pdo->prepare("UPDATE offers SET times_redeemed = times_redeemed + 1 WHERE id = ?")->execute([$offer_id]);

        jsonResponse(['status' => 'success', 'message' => 'Offer redeemed for ' . $customer_name, 'customer_name' => $customer_name]);
    }

    // GET OFFERS FOR PHONE (Manager — lookup unredeemed offers sent to a phone number)
    if ($action === 'get_offers_for_phone' && $method === 'GET') {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            jsonResponse(['error' => 'Unauthorized']);
        }

        $phone = preg_replace('/[^0-9]/', '', $_GET['phone'] ?? '');
        if (strlen($phone) < 9) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid phone number']);
        }

        // Auto-expire old offers
        $pdo->exec("UPDATE offers SET status = 'expired' WHERE status = 'active' AND valid_until < NOW()");

        try {
            // Check if tracking slugs table exists
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'offer_tracking_slugs'");
            if ($tableCheck->rowCount() === 0) {
                // Table doesn't exist - no offers have been sent yet
                // Lookup customer name anyway
                $nameStmt = $pdo->prepare("SELECT name FROM transfers WHERE phone = ? ORDER BY id DESC LIMIT 1");
                $nameStmt->execute([$phone]);
                $customerName = $nameStmt->fetchColumn() ?: null;

                jsonResponse([
                    'status' => 'success',
                    'offers' => [],
                    'customer_name' => $customerName,
                    'total_found' => 0,
                    'available' => 0
                ]);
            }

            // Find offers that were sent to this phone via tracking slugs
            $stmt = $pdo->prepare("
                SELECT DISTINCT o.*, ots.slug as tracking_slug, ots.created_at as sent_at,
                    (SELECT COUNT(*) FROM offer_redemptions r WHERE r.offer_id = o.id AND r.customer_phone = ?) as already_redeemed
                FROM offers o
                INNER JOIN offer_tracking_slugs ots ON ots.offer_id = o.id AND ots.phone = ?
                WHERE o.status = 'active'
                ORDER BY sent_at DESC
            ");
            $stmt->execute([$phone, $phone]);
            $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Lookup customer name
            $nameStmt = $pdo->prepare("SELECT name FROM transfers WHERE phone = ? ORDER BY id DESC LIMIT 1");
            $nameStmt->execute([$phone]);
            $customerName = $nameStmt->fetchColumn() ?: null;

            // Filter out already redeemed and exhausted
            $available = [];
            foreach ($offers as $o) {
                if ($o['already_redeemed'] > 0) continue;
                if ($o['max_redemptions'] !== null && $o['times_redeemed'] >= $o['max_redemptions']) continue;
                $available[] = $o;
            }

            jsonResponse([
                'status' => 'success',
                'offers' => $available,
                'customer_name' => $customerName,
                'total_found' => count($offers),
                'available' => count($available)
            ]);
        } catch (Exception $e) {
            error_log("Get offers for phone error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    // GET OFFER REDEMPTIONS (Manager — auth required)
    if ($action === 'get_offer_redemptions' && $method === 'GET') {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            jsonResponse(['error' => 'Unauthorized']);
        }

        $offer_id = intval($_GET['offer_id'] ?? 0);
        if ($offer_id <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid offer ID']);
        }

        // Join with users table to get operator name
        $stmt = $pdo->prepare("
            SELECT r.*, u.full_name as redeemed_by_name 
            FROM offer_redemptions r 
            LEFT JOIN users u ON r.redeemed_by = u.id 
            WHERE r.offer_id = ? 
            ORDER BY r.redeemed_at DESC
        ");
        $stmt->execute([$offer_id]);
        $redemptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(['status' => 'success', 'redemptions' => $redemptions]);
    }

    // =================================================================
    // CASE VERSIONS API
    // =================================================================

    /**
     * Sync active version's parts, labor, discounts, VAT and computed totals
     * back to the transfers row so the whole case reflects the active invoice.
     */
    function syncActiveVersionToTransfer($pdo, $transfer_id) {
        $stmt = $pdo->prepare("SELECT * FROM case_versions WHERE transfer_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$transfer_id]);
        $v = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$v) return; // no active version — nothing to sync

        $repairParts = json_decode($v['repair_parts'] ?? '[]', true) ?: [];
        $repairLabor = json_decode($v['repair_labor'] ?? '[]', true) ?: [];
        $partsDisc   = floatval($v['parts_discount_percent'] ?? 0);
        $servicesDisc = floatval($v['services_discount_percent'] ?? 0);
        $globalDisc  = floatval($v['global_discount_percent'] ?? 0);
        $vatEnabled  = !empty($v['vat_enabled']);

        // Compute item-level totals (same formula as get_case_versions / public_invoice)
        $partsTotal = 0;
        foreach ($repairParts as $p) {
            $qty   = floatval($p['quantity'] ?? 1);
            $price = floatval($p['unit_price'] ?? 0);
            $disc  = floatval($p['discount_percent'] ?? 0);
            $partsTotal += $qty * $price * (1 - $disc / 100);
        }
        $laborTotal = 0;
        foreach ($repairLabor as $l) {
            $qty  = floatval($l['quantity'] ?? $l['hours'] ?? 1);
            $rate = floatval($l['unit_rate'] ?? $l['hourly_rate'] ?? 0);
            $disc = floatval($l['discount_percent'] ?? 0);
            $laborTotal += $qty * $rate * (1 - $disc / 100);
        }

        // Apply category and global discounts
        $afterParts = $partsTotal * (1 - $partsDisc / 100);
        $afterLabor = $laborTotal * (1 - $servicesDisc / 100);
        $subtotalBeforeVat = round(($afterParts + $afterLabor) * (1 - $globalDisc / 100), 2);

        // Compute VAT
        $vatRate   = $vatEnabled ? 18 : 0;
        $vatAmount = $vatEnabled ? round($subtotalBeforeVat * 0.18, 2) : 0;
        $grandTotal = round($subtotalBeforeVat + $vatAmount, 2);

        // Update the transfers row with all active version data
        $stmt = $pdo->prepare("
            UPDATE transfers SET
                amount = ?,
                repair_parts = ?,
                repair_labor = ?,
                parts_discount_percent = ?,
                services_discount_percent = ?,
                global_discount_percent = ?,
                vat_enabled = ?,
                vat_amount = ?,
                vat_rate = ?,
                subtotal_before_vat = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $grandTotal,
            $v['repair_parts'],
            $v['repair_labor'],
            $partsDisc,
            $servicesDisc,
            $globalDisc,
            $vatEnabled ? 1 : 0,
            $vatAmount,
            $vatRate,
            $subtotalBeforeVat,
            $transfer_id
        ]);
    }

    // GET all versions for a case
    if ($action === 'get_case_versions' && $method === 'GET') {
        $transfer_id = intval($_GET['transfer_id'] ?? 0);
        if ($transfer_id <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid transfer ID']);
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM case_versions WHERE transfer_id = ? ORDER BY is_active DESC, created_at DESC");
            $stmt->execute([$transfer_id]);
            $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decode JSON fields
            foreach ($versions as &$v) {
                $v['repair_parts'] = json_decode($v['repair_parts'] ?? '[]', true) ?: [];
                $v['repair_labor'] = json_decode($v['repair_labor'] ?? '[]', true) ?: [];
                $v['parts_discount_percent'] = floatval($v['parts_discount_percent']);
                $v['services_discount_percent'] = floatval($v['services_discount_percent']);
                $v['global_discount_percent'] = floatval($v['global_discount_percent']);
                $v['vat_enabled'] = (bool)$v['vat_enabled'];
                $v['is_active'] = (bool)$v['is_active'];

                // Compute totals
                $partsTotal = 0;
                foreach ($v['repair_parts'] as $p) {
                    $qty = floatval($p['quantity'] ?? 1);
                    $price = floatval($p['unit_price'] ?? 0);
                    $disc = floatval($p['discount_percent'] ?? 0);
                    $partsTotal += $qty * $price * (1 - $disc / 100);
                }
                $laborTotal = 0;
                foreach ($v['repair_labor'] as $l) {
                    $qty = floatval($l['quantity'] ?? $l['hours'] ?? 1);
                    $rate = floatval($l['unit_rate'] ?? $l['hourly_rate'] ?? 0);
                    $disc = floatval($l['discount_percent'] ?? 0);
                    $laborTotal += $qty * $rate * (1 - $disc / 100);
                }
                $afterParts = $partsTotal * (1 - $v['parts_discount_percent'] / 100);
                $afterLabor = $laborTotal * (1 - $v['services_discount_percent'] / 100);
                $afterCategory = $afterParts + $afterLabor;
                $grandTotal = $afterCategory * (1 - $v['global_discount_percent'] / 100);
                if ($v['vat_enabled']) $grandTotal *= 1.18;
                $v['computed_total'] = round($grandTotal, 2);
                $v['parts_count'] = count($v['repair_parts']);
                $v['labor_count'] = count($v['repair_labor']);
            }
            unset($v);

            jsonResponse(['status' => 'success', 'versions' => $versions]);
        } catch (Exception $e) {
            error_log("get_case_versions error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error occurred']);
        }
    }

    // CREATE a new version (optionally copying from transfers or another version)
    if ($action === 'create_case_version' && $method === 'POST') {
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['error' => 'Manager access required']);
        }

        $data = getJsonInput();
        $transfer_id = intval($data['transfer_id'] ?? 0);
        $version_name = trim($data['version_name'] ?? '');
        $copy_from = $data['copy_from'] ?? null; // 'current' or version_id

        if ($transfer_id <= 0 || !$version_name) {
            jsonResponse(['status' => 'error', 'message' => 'transfer_id and version_name are required']);
        }

        try {
            $repair_parts = [];
            $repair_labor = [];
            $parts_disc = 0;
            $services_disc = 0;
            $global_disc = 0;
            $vat_enabled = 0;

            if ($copy_from === 'current') {
                // Copy from the transfers row
                $stmt = $pdo->prepare("SELECT repair_parts, repair_labor, parts_discount_percent, services_discount_percent, global_discount_percent, vat_enabled FROM transfers WHERE id = ?");
                $stmt->execute([$transfer_id]);
                $src = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($src) {
                    $repair_parts = json_decode($src['repair_parts'] ?? '[]', true) ?: [];
                    $repair_labor = json_decode($src['repair_labor'] ?? '[]', true) ?: [];
                    $parts_disc = floatval($src['parts_discount_percent'] ?? 0);
                    $services_disc = floatval($src['services_discount_percent'] ?? 0);
                    $global_disc = floatval($src['global_discount_percent'] ?? 0);
                    $vat_enabled = !empty($src['vat_enabled']) ? 1 : 0;
                }
            } elseif (is_numeric($copy_from) && intval($copy_from) > 0) {
                // Copy from another version
                $stmt = $pdo->prepare("SELECT repair_parts, repair_labor, parts_discount_percent, services_discount_percent, global_discount_percent, vat_enabled FROM case_versions WHERE id = ? AND transfer_id = ?");
                $stmt->execute([intval($copy_from), $transfer_id]);
                $src = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($src) {
                    $repair_parts = json_decode($src['repair_parts'] ?? '[]', true) ?: [];
                    $repair_labor = json_decode($src['repair_labor'] ?? '[]', true) ?: [];
                    $parts_disc = floatval($src['parts_discount_percent'] ?? 0);
                    $services_disc = floatval($src['services_discount_percent'] ?? 0);
                    $global_disc = floatval($src['global_discount_percent'] ?? 0);
                    $vat_enabled = !empty($src['vat_enabled']) ? 1 : 0;
                }
            }
            // else: blank version

            // Clamp discount ranges to 0-100
            $parts_disc = max(0, min(100, floatval($parts_disc)));
            $services_disc = max(0, min(100, floatval($services_disc)));
            $global_disc = max(0, min(100, floatval($global_disc)));

            // Check if this is the first version — auto-set as active
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM case_versions WHERE transfer_id = ?");
            $stmt->execute([$transfer_id]);
            $existingCount = $stmt->fetchColumn();
            $is_active = ($existingCount == 0) ? 1 : 0;

            $stmt = $pdo->prepare("INSERT INTO case_versions (transfer_id, version_name, repair_parts, repair_labor, parts_discount_percent, services_discount_percent, global_discount_percent, vat_enabled, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $transfer_id,
                $version_name,
                json_encode($repair_parts),
                json_encode($repair_labor),
                $parts_disc,
                $services_disc,
                $global_disc,
                $vat_enabled,
                $is_active,
                getCurrentUserId()
            ]);

            // If this is the first (auto-active) version, sync to transfers
            if ($is_active) {
                syncActiveVersionToTransfer($pdo, $transfer_id);
            }

            jsonResponse(['status' => 'success', 'version_id' => $pdo->lastInsertId(), 'is_active' => (bool)$is_active]);
        } catch (Exception $e) {
            error_log("create_case_version error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error occurred']);
        }
    }

    // UPDATE a version's data
    if ($action === 'update_case_version' && $method === 'POST') {
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['error' => 'Manager access required']);
        }

        $data = getJsonInput();
        $version_id = intval($data['id'] ?? $_GET['id'] ?? 0);
        if ($version_id <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Version ID is required']);
        }

        try {
            // Server-side validation: version_name must not be empty
            if (array_key_exists('version_name', $data) && empty(trim($data['version_name'] ?? ''))) {
                jsonResponse(['status' => 'error', 'message' => 'Version name cannot be empty']);
            }

            // Clamp discount ranges to 0-100
            foreach (['parts_discount_percent', 'services_discount_percent', 'global_discount_percent'] as $discField) {
                if (array_key_exists($discField, $data)) {
                    $data[$discField] = max(0, min(100, floatval($data[$discField])));
                }
            }

            $updates = [];
            $params = [];

            $allowed = ['version_name', 'repair_parts', 'repair_labor', 'parts_discount_percent', 'services_discount_percent', 'global_discount_percent', 'vat_enabled', 'notes'];
            foreach ($allowed as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "`$field` = ?";
                    if (is_array($data[$field])) {
                        $params[] = json_encode($data[$field]);
                    } elseif ($field === 'vat_enabled') {
                        $params[] = $data[$field] ? 1 : 0;
                    } else {
                        $params[] = $data[$field];
                    }
                }
            }

            if (empty($updates)) {
                jsonResponse(['status' => 'error', 'message' => 'No fields to update']);
            }

            $params[] = $version_id;
            $sql = "UPDATE case_versions SET " . implode(', ', $updates) . " WHERE id = ?";
            $pdo->prepare($sql)->execute($params);

            // If this version is active, sync updated data to transfers
            $stmt = $pdo->prepare("SELECT transfer_id, is_active FROM case_versions WHERE id = ?");
            $stmt->execute([$version_id]);
            $versionRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($versionRow && $versionRow['is_active']) {
                syncActiveVersionToTransfer($pdo, $versionRow['transfer_id']);
            }

            jsonResponse(['status' => 'success']);
        } catch (Exception $e) {
            error_log("update_case_version error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error occurred']);
        }
    }

    // SET a version as active (deactivates all others for that case)
    if ($action === 'set_active_version' && $method === 'POST') {
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['error' => 'Manager access required']);
        }

        $data = getJsonInput();
        $version_id = intval($data['id'] ?? $_GET['id'] ?? 0);
        if ($version_id <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Version ID is required']);
        }

        try {
            // Get transfer_id from version
            $stmt = $pdo->prepare("SELECT transfer_id FROM case_versions WHERE id = ?");
            $stmt->execute([$version_id]);
            $transfer_id = $stmt->fetchColumn();
            if (!$transfer_id) {
                jsonResponse(['status' => 'error', 'message' => 'Version not found']);
            }

            // Use transaction to prevent orphaned state if second UPDATE fails
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE case_versions SET is_active = 0 WHERE transfer_id = ?")->execute([$transfer_id]);
            $pdo->prepare("UPDATE case_versions SET is_active = 1 WHERE id = ?")->execute([$version_id]);
            // Sync active version data (parts, labor, discounts, total) to transfers row
            syncActiveVersionToTransfer($pdo, $transfer_id);
            $pdo->commit();

            jsonResponse(['status' => 'success']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("set_active_version error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error occurred']);
        }
    }

    // DELETE a version
    if ($action === 'delete_case_version' && $method === 'POST') {
        if (!checkPermission('manager')) {
            http_response_code(403);
            jsonResponse(['error' => 'Manager access required']);
        }

        $data = getJsonInput();
        $version_id = intval($data['id'] ?? $_GET['id'] ?? 0);
        if ($version_id <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Version ID is required']);
        }

        try {
            // Prevent deleting the active version
            $stmt = $pdo->prepare("SELECT is_active, transfer_id FROM case_versions WHERE id = ?");
            $stmt->execute([$version_id]);
            $version = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$version) {
                jsonResponse(['status' => 'error', 'message' => 'Version not found']);
            }
            if ($version['is_active']) {
                jsonResponse(['status' => 'error', 'message' => 'Cannot delete the active version. Set another version as active first.']);
            }

            $pdo->prepare("DELETE FROM case_versions WHERE id = ?")->execute([$version_id]);
            jsonResponse(['status' => 'success']);
        } catch (Exception $e) {
            error_log("delete_case_version error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error occurred']);
        }
    }

    // GET a single version by ID (for public invoice)
    if ($action === 'get_case_version' && $method === 'GET') {
        $version_id = intval($_GET['id'] ?? 0);
        if ($version_id <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Version ID is required']);
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM case_versions WHERE id = ?");
            $stmt->execute([$version_id]);
            $v = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$v) {
                jsonResponse(['status' => 'error', 'message' => 'Version not found']);
            }

            $v['repair_parts'] = json_decode($v['repair_parts'] ?? '[]', true) ?: [];
            $v['repair_labor'] = json_decode($v['repair_labor'] ?? '[]', true) ?: [];
            $v['vat_enabled'] = (bool)$v['vat_enabled'];
            $v['is_active'] = (bool)$v['is_active'];

            jsonResponse(['status' => 'success', 'version' => $v]);
        } catch (Exception $e) {
            error_log("get_case_version error: " . $e->getMessage());
            jsonResponse(['status' => 'error', 'message' => 'Database error occurred']);
        }
    }

    // --- SAVE COMPLETION SIGNATURE (Public) ---
    if ($action === 'save_completion_signature' && $method === 'POST') {
        $data = getJsonInput();
        $slug = trim($data['slug'] ?? '');
        $signature = $data['signature'] ?? '';

        // Validate slug
        if (empty($slug) || !preg_match('/^[a-f0-9]{32}$/', $slug)) {
            http_response_code(400);
            jsonResponse(['error' => 'Invalid request']);
        }

        // Validate signature data (must be a base64 PNG data URL)
        if (empty($signature) || !preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $signature)) {
            http_response_code(400);
            jsonResponse(['error' => 'Invalid signature data']);
        }

        // Limit signature size (max ~500KB base64)
        if (strlen($signature) > 500000) {
            http_response_code(400);
            jsonResponse(['error' => 'Signature data too large']);
        }

        try {
            // Fetch the case by slug
            $stmt = $pdo->prepare("SELECT id, status, status_id, completion_signature FROM transfers WHERE slug = ?");
            $stmt->execute([$slug]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transfer) {
                http_response_code(404);
                jsonResponse(['error' => 'Case not found']);
            }

            // Only allow signing for completed cases (check both status name and status_id=8)
            $isCompleted = strtolower($transfer['status'] ?? '') === 'completed' || intval($transfer['status_id'] ?? 0) === 8;
            if (!$isCompleted) {
                http_response_code(400);
                jsonResponse(['error' => 'Case must be completed before signing']);
            }

            // Prevent overwriting existing signature
            if (!empty($transfer['completion_signature'])) {
                http_response_code(400);
                jsonResponse(['error' => 'This case has already been signed']);
            }

            // Save signature
            $stmt = $pdo->prepare("UPDATE transfers SET completion_signature = ?, signature_date = NOW() WHERE slug = ?");
            $stmt->execute([$signature, $slug]);

            // Log the signature event
            $logStmt = $pdo->prepare("SELECT systemLogs FROM transfers WHERE slug = ?");
            $logStmt->execute([$slug]);
            $logRow = $logStmt->fetch(PDO::FETCH_ASSOC);
            $logs = json_decode($logRow['systemLogs'] ?? '[]', true) ?: [];
            $logs[] = [
                'timestamp' => date('c'),
                'message' => 'Customer signed completion confirmation digitally'
            ];
            $pdo->prepare("UPDATE transfers SET systemLogs = ? WHERE slug = ?")->execute([json_encode($logs), $slug]);

            jsonResponse(['status' => 'success', 'message' => 'Signature saved successfully']);
        } catch (Exception $e) {
            error_log('save_completion_signature error: ' . $e->getMessage());
            http_response_code(500);
            jsonResponse(['error' => 'Failed to save signature']);
        }
    }

    // Default response if no action matched
    jsonResponse(['error' => 'Unknown action: ' . $action]);

} catch (Exception $e) {
    error_log('Unhandled API error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'An unexpected error occurred']);
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