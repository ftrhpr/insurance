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
        
        // Ensure link_opened_at column exists
        try {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'transfers' AND COLUMN_NAME = 'link_opened_at'");
            $checkStmt->execute([DB_NAME]);
            if ($checkStmt->fetchColumn() == 0) {
                $pdo->exec("ALTER TABLE transfers ADD COLUMN `link_opened_at` DATETIME DEFAULT NULL");
            }
        } catch (Exception $e) {
            // Column might already exist
        }
        
        // Record link open (only first time)
        $pdo->prepare("UPDATE transfers SET link_opened_at = COALESCE(link_opened_at, NOW()) WHERE id = ? AND link_opened_at IS NULL")->execute([$id]);
        
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

            $stmt = $pdo->prepare("INSERT INTO transfers (plate, name, amount, franchise, rawText, phone, vehicle_make, vehicle_model, status, created_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'New', NOW())");
            
            $stmt->execute([
                $plate,
                trim($data['name']), 
                $amount,
                isset($data['franchise']) ? trim($data['franchise']) : '',
                isset($data['rawText']) ? trim($data['rawText']) : '',
                $phoneFromVehicle,
                isset($data['vehicleMake']) ? trim($data['vehicleMake']) : null,
                isset($data['vehicleModel']) ? trim($data['vehicleModel']) : null
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

        // Ensure transfers table has repair management columns (defensive migration compatible with older MySQL)
        try {
            $required = [
                'repair_status' => "VARCHAR(50) DEFAULT NULL",
                'repair_start_date' => "DATETIME DEFAULT NULL",
                'repair_end_date' => "DATETIME DEFAULT NULL",
                'assigned_mechanic' => "VARCHAR(100) DEFAULT NULL",
                'repair_notes' => "TEXT DEFAULT NULL",
                'repair_parts' => "TEXT DEFAULT NULL",
                'repair_labor' => "TEXT DEFAULT NULL",
                'repair_activity_log' => "TEXT DEFAULT NULL",
                'vehicle_make' => "VARCHAR(100) DEFAULT NULL",
                'vehicle_model' => "VARCHAR(100) DEFAULT NULL",
                'case_images' => "TEXT DEFAULT NULL",
                'parts_discount_percent' => "DECIMAL(5,2) DEFAULT 0",
                'services_discount_percent' => "DECIMAL(5,2) DEFAULT 0",
                'global_discount_percent' => "DECIMAL(5,2) DEFAULT 0",
                'slug' => "VARCHAR(32) UNIQUE DEFAULT NULL",
                'vat_enabled' => "TINYINT(1) DEFAULT 0",
                'vat_amount' => "DECIMAL(10,2) DEFAULT 0.00",
                'case_type' => "ENUM('საცალო', 'დაზღვევა') DEFAULT 'საცალო'",
                'nachrebi_qty' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Pieces quantity (ნაჭრების რაოდენობა)'"
            ];
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'transfers' AND COLUMN_NAME = ?");
            foreach ($required as $col => $def) {
                try {
                    $checkStmt->execute([DB_NAME, $col]);
                    $exists = $checkStmt->fetchColumn();
                    if ($exists == 0) {
                        try {
                            $pdo->exec("ALTER TABLE transfers ADD COLUMN `$col` $def");
                            error_log("Successfully added column: $col");
                        } catch (PDOException $alterError) {
                            error_log("Failed to add column $col: " . $alterError->getMessage());
                            // Try alternative syntax for some MySQL versions
                            try {
                                $pdo->exec("ALTER TABLE transfers ADD `$col` $def");
                                error_log("Successfully added column with alternative syntax: $col");
                            } catch (PDOException $altError) {
                                error_log("Alternative syntax also failed for column $col: " . $altError->getMessage());
                            }
                        }
                    }
                } catch (PDOException $checkError) {
                    error_log("Failed to check column $col: " . $checkError->getMessage());
                    // If we can't check, try to add anyway (will fail silently if exists)
                    try {
                        $pdo->exec("ALTER TABLE transfers ADD COLUMN `$col` $def");
                        error_log("Added column without checking: $col");
                    } catch (PDOException $e) {
                        // Column probably already exists, ignore
                        error_log("Column $col already exists or add failed: " . $e->getMessage());
                    }
                }
            }
        } catch (Exception $alterError) {
            error_log("Error in defensive column migration: " . $alterError->getMessage());
            // Continue anyway - columns might already exist or ALTER might fail on some DB versions
        }

        try {
            $field_map = [
                'name' => 'name',
                'plate' => 'plate',
                'amount' => 'amount',
                'case_type' => 'case_type',
                'status' => 'status',
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
                    jsonResponse(['error' => 'Database error during update: ' . $e->getMessage()]);
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
                $pdo->prepare("UPDATE transfers SET status = 'Scheduled', service_date = ?, user_response = 'Pending' WHERE id = ?")
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
        // Ensure transfers table has repair management columns (defensive migration compatible with older MySQL)
        try {
            $required = [
                'repair_status' => "VARCHAR(50) DEFAULT NULL",
                'repair_start_date' => "DATETIME DEFAULT NULL",
                'repair_end_date' => "DATETIME DEFAULT NULL",
                'assigned_mechanic' => "VARCHAR(100) DEFAULT NULL",
                'repair_notes' => "TEXT DEFAULT NULL",
                'repair_parts' => "TEXT DEFAULT NULL",
                'repair_labor' => "TEXT DEFAULT NULL",
                'repair_activity_log' => "TEXT DEFAULT NULL",
                'link_opened_at' => "DATETIME DEFAULT NULL",
                'work_times' => "JSON DEFAULT NULL",
                'assignment_history' => "JSON DEFAULT NULL",
                'operatorComment' => "TEXT DEFAULT NULL",
            ];
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'transfers' AND COLUMN_NAME = ?");
            foreach ($required as $col => $def) {
                $checkStmt->execute([DB_NAME, $col]);
                if ($checkStmt->fetchColumn() == 0) {
                    $pdo->exec("ALTER TABLE transfers ADD COLUMN `$col` $def");
                }
            }
        } catch (Exception $alterError) {
            // Continue anyway - columns might already exist or ALTER might fail on some DB versions
        }

        // Pagination support
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        
        // Build query with optional pagination
        $query = "SELECT *, service_date as serviceDate, user_response as user_response, review_stars as reviewStars, review_comment as reviewComment, reschedule_date as rescheduleDate, reschedule_comment as rescheduleComment, link_opened_at as linkOpenedAt, operatorComment, repair_status FROM transfers WHERE status IN ('New', 'Processing', 'Called', 'Parts Ordered', 'Parts Arrived', 'Scheduled', 'Already in service', 'Completed') ORDER BY created_at DESC";
        
        if ($limit > 0) {
            $query .= " LIMIT " . $limit . " OFFSET " . $offset;
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count for pagination
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM transfers WHERE status IN ('New', 'Processing', 'Called', 'Parts Ordered', 'Parts Arrived', 'Scheduled', 'Already in service', 'Completed')");
        $countStmt->execute();
        $totalCount = $countStmt->fetchColumn();
        foreach ($rows as &$row) {
            $row['internalNotes'] = json_decode($row['internalNotes'] ?? '[]');
            $row['systemLogs'] = json_decode($row['systemLogs'] ?? '[]');
            // serviceDate is already correctly named in the database
        }
        
        // Also get vehicles for vehicle DB page (only on initial load)
        $vehicles = [];
        if ($offset === 0) {
            $vehicleStmt = $pdo->prepare("SELECT * FROM vehicles ORDER BY plate ASC");
            $vehicleStmt->execute();
            $vehicles = $vehicleStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $hasMore = ($limit > 0) ? (count($rows) === $limit && ($offset + $limit) < $totalCount) : false;
        
        jsonResponse([
            'transfers' => $rows,
            'vehicles' => $vehicles,
            'hasMore' => $hasMore,
            'total' => $limit > 0 ? $totalCount : count($rows)
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
            jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    // Get single transfer with logs and work times
    if ($action === 'get_transfer' && $method === 'GET') {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) jsonResponse(['status' => 'error', 'message' => 'Invalid id']);
        $stmt = $pdo->prepare("SELECT id, plate, name, phone, amount, franchise, nachrebi_qty, status, case_type, service_date, vehicle_make, vehicle_model FROM transfers WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) jsonResponse(['status' => 'error', 'message' => 'Not found']);
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
        
        if (!in_array($role, ['admin', 'manager', 'viewer', 'technician'])) {
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
        if ($role && in_array($role, ['admin', 'manager', 'viewer', 'technician'])) {
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
            // Include helpful debug in response for admins
            jsonResponse(['error' => 'transfer_id and positive amount are required', 'debug' => ['data' => $data, '_POST' => $_POST]]);
        }

        // Ensure payments table exists (defensive)
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                transfer_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                method ENUM('cash','transfer') NOT NULL DEFAULT 'cash',
                reference VARCHAR(255) DEFAULT NULL,
                recorded_by INT DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                currency VARCHAR(3) DEFAULT 'GEL',
                paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (transfer_id) REFERENCES transfers(id) ON DELETE CASCADE,
                FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Exception $e) { /* ignore */ }

        // Defensive: ensure payments table has expected columns (for older DBs)
        try {
            $colCheck = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'payments' AND COLUMN_NAME = ?");
            $schema = DB_NAME;
            $paymentsCols = [
                'method' => "ENUM('cash','transfer') NOT NULL DEFAULT 'cash'",
                'reference' => "VARCHAR(255) DEFAULT NULL",
                'recorded_by' => "INT DEFAULT NULL",
                'notes' => "TEXT DEFAULT NULL",
                'currency' => "VARCHAR(3) DEFAULT 'GEL'",
                'paid_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
                'payment_date' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"  // For legacy schemas
            ];
            foreach ($paymentsCols as $col => $def) {
                $colCheck->execute([$schema, $col]);
                if ($colCheck->fetchColumn() == 0) {
                    try {
                        $pdo->exec("ALTER TABLE payments ADD COLUMN $col $def");
                        error_log("Added missing payments column: $col");
                    } catch (Exception $e) {
                        error_log("Failed to add payments column $col: " . $e->getMessage());
                    }
                }
            }
        } catch (Exception $e) {
            // ignore
        }

        // Special handling for legacy payment_date column
        try {
            $checkDefault = $pdo->prepare("SELECT COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'payment_date'");
            $checkDefault->execute([DB_NAME]);
            $default = $checkDefault->fetchColumn();
            if ($default === null || $default === '') {
                $pdo->exec("ALTER TABLE payments MODIFY COLUMN payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                error_log("Modified payment_date column to have default timestamp");
            }
        } catch (Exception $e) {
            error_log("Failed to check/modify payment_date default: " . $e->getMessage());
        }

        $stmt = $pdo->prepare("SELECT id, amount, COALESCE(amount_paid,0) as amount_paid FROM transfers WHERE id = ? LIMIT 1");
        $stmt->execute([$transfer_id]);
        $tr = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tr) {
            http_response_code(404);
            jsonResponse(['error' => 'Transfer not found']);
        }
        // Build a dynamic insert to support legacy DBs (payment_date vs paid_at, missing recorded_by etc.)
        $recorded_by = getCurrentUserId();

        // Ensure method column exists
        try {
            $pdo->exec("ALTER TABLE payments ADD COLUMN method ENUM('cash','transfer') NOT NULL DEFAULT 'cash'");
        } catch (Exception $e) { /* ignore */ }

        // Find existing payments columns
        $colsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'payments'");
        $colsStmt->execute([DB_NAME]);
        $existingCols = array_column($colsStmt->fetchAll(PDO::FETCH_COLUMN), 0);

        $insertCols = ['transfer_id', 'amount', 'method'];
        $insertParams = [$transfer_id, $amount, $methodType];

        if (in_array('reference', $existingCols)) { $insertCols[] = 'reference'; $insertParams[] = $reference; }
        if (in_array('recorded_by', $existingCols)) { $insertCols[] = 'recorded_by'; $insertParams[] = $recorded_by; }
        if (in_array('notes', $existingCols)) { $insertCols[] = 'notes'; $insertParams[] = $notes; }
        if (in_array('currency', $existingCols)) { $insertCols[] = 'currency'; $insertParams[] = 'GEL'; }
        // handle payment timestamp column variations
        if (in_array('paid_at', $existingCols)) { $insertCols[] = 'paid_at'; $insertParams[] = date('Y-m-d H:i:s'); }
        // Always include payment_date if present to avoid NOT NULL without default errors on legacy schemas
        if (in_array('payment_date', $existingCols)) { $insertCols[] = 'payment_date'; $insertParams[] = date('Y-m-d H:i:s'); }
        // created_at as a fallback (if no explicit paid date columns exist)
        if (in_array('created_at', $existingCols) && !in_array('paid_at', $existingCols) && !in_array('payment_date', $existingCols)) { $insertCols[] = 'created_at'; $insertParams[] = date('Y-m-d H:i:s'); }

        $placeholders = rtrim(str_repeat('?,', count($insertCols)), ',');
        $sql = "INSERT INTO payments (" . implode(', ', $insertCols) . ") VALUES (" . $placeholders . ")";
        $insert = $pdo->prepare($sql);

        // Log SQL and params for debugging
        error_log('create_payment about to execute SQL: ' . $sql . ' Params: ' . json_encode($insertParams));
        try {
            $insert->execute($insertParams);
        } catch (Exception $e) {
            $errMsg = $e->getMessage();
            error_log('create_payment insert failed: ' . $errMsg . ' SQL: ' . $sql . ' Params: ' . json_encode($insertParams));
            http_response_code(500);
            jsonResponse(['error' => 'Failed to save payment', 'debug' => $errMsg, 'sql' => $sql, 'params' => $insertParams]);
        }

        $payment_id = $pdo->lastInsertId();

        // Update transfer paid totals
        $new_paid = floatval($tr['amount_paid']) + $amount;
        $status = (!is_null($tr['amount']) && floatval($new_paid) >= floatval($tr['amount'])) ? 'paid' : ($new_paid > 0 ? 'partial' : 'unpaid');
        // Ensure transfer columns exist
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'transfers' AND COLUMN_NAME = ?");
        $schema = DB_NAME;
        foreach (['amount_paid','payment_status','last_payment_at'] as $col) {
            $checkStmt->execute([$schema, $col]);
            if ($checkStmt->fetchColumn() == 0) {
                if ($col === 'amount_paid') $pdo->exec("ALTER TABLE transfers ADD COLUMN amount_paid DECIMAL(10,2) DEFAULT 0.00");
                if ($col === 'payment_status') $pdo->exec("ALTER TABLE transfers ADD COLUMN payment_status ENUM('unpaid','partial','paid') DEFAULT 'unpaid'");
                if ($col === 'last_payment_at') $pdo->exec("ALTER TABLE transfers ADD COLUMN last_payment_at DATETIME DEFAULT NULL");
            }
        }
        $updateStmt = $pdo->prepare("UPDATE transfers SET amount_paid = ?, payment_status = ?, last_payment_at = NOW() WHERE id = ?");
        $updateStmt->execute([number_format($new_paid,2,'.',''), $status, $transfer_id]);

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

        // Delete the payment
        $deleteStmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
        $deleteStmt->execute([$payment_id]);

        // Update transfer paid totals
        $stmt = $pdo->prepare("SELECT amount, COALESCE(amount_paid,0) as amount_paid FROM transfers WHERE id = ?");
        $stmt->execute([$transfer_id]);
        $tr = $stmt->fetch(PDO::FETCH_ASSOC);
        $new_paid = floatval($tr['amount_paid']) - $deleted_amount;
        $status = (!is_null($tr['amount']) && floatval($new_paid) >= floatval($tr['amount'])) ? 'paid' : ($new_paid > 0 ? 'partial' : 'unpaid');

        $updateStmt = $pdo->prepare("UPDATE transfers SET amount_paid = ?, payment_status = ? WHERE id = ?");
        $updateStmt->execute([number_format($new_paid,2,'.',''), $status, $transfer_id]);

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
            jsonResponse(['status' => 'error', 'message' => 'Failed to delete vehicle: ' . $e->getMessage()]);
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
            jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
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
            jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
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
            jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
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
            jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
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
            jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
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