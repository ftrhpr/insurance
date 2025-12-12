// --- PDF INVOICE PARSING ENDPOINT ---
if ($action === 'parse_invoice_pdf' && $method === 'POST') {
    // Check for file upload
    if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        jsonResponse(['error' => 'No PDF uploaded or upload error.']);
    }

    // Check for PDF parser library
    $autoloadPath = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        http_response_code(500);
        jsonResponse(['error' => 'PDF parser library not installed. Please run composer require smalot/pdfparser.']);
    }
    require_once $autoloadPath;

    $pdfFile = $_FILES['pdf']['tmp_name'];
    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($pdfFile);
        $text = $pdf->getText();
    } catch (Exception $e) {
        http_response_code(500);
        jsonResponse(['error' => 'Failed to parse PDF: ' . $e->getMessage()]);
    }

    // Simple regex-based extraction: lines like "Part Name xQty – ₾Price" or "Labor: Name – ₾Price"
    $lines = preg_split('/\r?\n/', $text);
    $items = [];
    foreach ($lines as $line) {
        $line = trim($line);
        // Match labor: "Labor: Name – ₾Price"
        if (preg_match('/^(Labor|Service)[:\-\s]+(.+?)\s*[–-]\s*[₾]?(\d+[\.,]?\d*)/iu', $line, $m)) {
            $items[] = [
                'type' => 'labor',
                'name' => trim($m[2]),
                'quantity' => 1,
                'price' => floatval(str_replace([','], ['.'], $m[3]))
            ];
            continue;
        }
        // Match part: "Part Name xQty – ₾Price" or "Name – ₾Price"
        if (preg_match('/^(.+?)\s*x(\d+)\s*[–-]\s*[₾]?(\d+[\.,]?\d*)/iu', $line, $m)) {
            $items[] = [
                'type' => 'part',
                'name' => trim($m[1]),
                'quantity' => intval($m[2]),
                'price' => floatval(str_replace([','], ['.'], $m[3]))
            ];
            continue;
        }
        // Fallback: "Name – ₾Price" (assume part, qty=1)
        if (preg_match('/^(.+?)\s*[–-]\s*[₾]?(\d+[\.,]?\d*)/iu', $line, $m)) {
            $items[] = [
                'type' => 'part',
                'name' => trim($m[1]),
                'quantity' => 1,
                'price' => floatval(str_replace([','], ['.'], $m[2]))
            ];
            continue;
        }
    }
    jsonResponse(['success' => true, 'items' => $items]);
}
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
        $stmt = $pdo->prepare("SELECT *, user_response as user_response, review_stars as reviewStars, review_comment as reviewComment, reschedule_date as rescheduleDate, reschedule_comment as rescheduleComment FROM transfers WHERE status IN ('New', 'Processing', 'Called', 'Parts Ordered', 'Parts Arrived', 'Scheduled') ORDER BY created_at DESC");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['internalNotes'] = json_decode($row['internal_notes'] ?? '[]');
            $row['systemLogs'] = json_decode($row['system_logs'] ?? '[]');
            $row['serviceDate'] = $row['service_date'] ?? null;
        }

        jsonResponse([
            'transfers' => $rows
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
        $data = getJsonInput();
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['status' => 'error', 'message' => 'Invalid ID']);
        }
        // Fetch existing transfer to detect status changes and get contact info
        $existingStmt = $pdo->prepare("SELECT name, plate, phone, amount, status FROM transfers WHERE id = ?");
        $existingStmt->execute([$id]);
        $existingTransfer = $existingStmt->fetch(PDO::FETCH_ASSOC);
        $fields = []; $params = [':id' => $id];
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
            // After update, if status changed to Completed, send review link SMS
            try {
                $newStatus = $data['status'] ?? ($existingTransfer['status'] ?? '');
                $oldStatus = $existingTransfer['status'] ?? '';
                if ($newStatus === 'Completed' && $oldStatus !== 'Completed') {
                    // Get latest transfer info
                    $stmt = $pdo->prepare("SELECT name, plate, phone, amount FROM transfers WHERE id = ?");
                    $stmt->execute([$id]);
                    $tr = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($tr && !empty($tr['phone'])) {
                        // Build review link
                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $link = $scheme . '://' . $host . dirname($_SERVER['PHP_SELF']) . '/public_view.php?id=' . $id;

                        // Load template
                        $tstmt = $pdo->prepare("SELECT content FROM sms_templates WHERE slug = 'completed'");
                        $tstmt->execute();
                        $template = $tstmt->fetchColumn();
                        if (!$template) {
                            $template = 'Service for {plate} is completed. Thank you for choosing OTOMOTORS! Rate your experience: {link}';
                        }

                        // Replace placeholders
                        $smsText = str_replace(
                            ['{name}', '{plate}', '{amount}', '{link}'],
                            [$tr['name'], $tr['plate'], $tr['amount'], $link],
                            $template
                        );

                        $api_key = defined('SMS_API_KEY') ? SMS_API_KEY : '';
                        $to = $tr['phone'];
                        @file_get_contents("https://api.gosms.ge/api/sendsms?api_key=$api_key&to=" . urlencode($to) . "&from=OTOMOTORS&text=" . urlencode($smsText));

                        // Append a system log entry to the transfer record
                        try {
                            $logStmt = $pdo->prepare("SELECT system_logs FROM transfers WHERE id = ?");
                            $logStmt->execute([$id]);
                            $current = $logStmt->fetchColumn();
                            $logs = [];
                            if ($current) {
                                $decoded = json_decode($current, true);
                                if (is_array($decoded)) $logs = $decoded;
                            }
                            $logs[] = ['message' => 'Completed SMS sent to customer', 'sms_to' => $to, 'timestamp' => date('c')];
                            $updateLogsStmt = $pdo->prepare("UPDATE transfers SET system_logs = ? WHERE id = ?");
                            $updateLogsStmt->execute([json_encode($logs), $id]);
                        } catch (Exception $elog) {
                            error_log('Failed to append system_logs after completed SMS: ' . $elog->getMessage());
                        }

                        // Send a manager push notification via FCM
                        try {
                            $fcmTitle = 'Order Completed';
                            $fcmBody = "Order #{$id} ({$tr['plate']}) marked Completed";
                            sendFCM_V1($pdo, $service_account_file, $fcmTitle, $fcmBody);
                        } catch (Exception $efcm) {
                            error_log('Failed to send FCM on completion: ' . $efcm->getMessage());
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Error sending completed SMS after update_transfer: ' . $e->getMessage());
            }
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
        // Check permissions - only admin and manager can delete
        $userRole = $_SESSION['role'] ?? 'viewer';
        if ($userRole !== 'admin' && $userRole !== 'manager') {
            http_response_code(403);
            jsonResponse(['status' => 'error', 'message' => 'Permission denied. Only managers and admins can delete orders.']);
            exit;
        }

        $id = intval($_GET['id'] ?? 0);
        if ($id > 0) {
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
        
        // Validation
        if (empty($data['plate'])) {
            jsonResponse(['status' => 'error', 'message' => 'Plate number is required']);
        }
        
        $plate = trim($data['plate']);
        $ownerName = trim($data['ownerName'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $model = trim($data['model'] ?? '');
        
        if (isset($_GET['id']) && $_GET['id']) {
            $id = intval($_GET['id']);
            $pdo->prepare("UPDATE vehicles SET plate=?, ownerName=?, phone=?, model=? WHERE id=?")->execute([$plate, $ownerName, $phone, $model, $id]);
        } else {
            $pdo->prepare("INSERT INTO vehicles (plate, ownerName, phone, model) VALUES (?, ?, ?, ?)")->execute([$plate, $ownerName, $phone, $model]);
        }
        jsonResponse(['status' => 'saved']);
    }
    if ($action === 'delete_vehicle' && $method === 'POST') {
        $id = intval($_GET['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM vehicles WHERE id=?")->execute([$id]);
            jsonResponse(['status' => 'deleted']);
        } else {
            jsonResponse(['status' => 'error', 'message' => 'Invalid vehicle ID']);
        }
    }
    if ($action === 'send_sms' && $method === 'POST') {
        $data = getJsonInput();
        $to = $data['to'] ?? ''; $text = $data['text'] ?? '';
        if (empty($to) || empty($text)) jsonResponse(['status' => 'error', 'message' => 'Missing data']);
        $api_key = defined('SMS_API_KEY') ? SMS_API_KEY : "5c88b0316e44d076d4677a4860959ef71ce049ce704b559355568a362f40ade1";
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

        $query = "SELECT pc.*, t.plate, t.name, u.username as assigned_manager_username, u.full_name as assigned_manager_name 
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

        jsonResponse(['collections' => $collections]);
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

        jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
    }

    if ($action === 'update_parts_collection' && $method === 'POST') {
        $data = getJsonInput();
        $id = $data['id'] ?? null;
        $parts_list = $data['parts_list'] ?? [];
        $status = $data['status'] ?? null;
        $assigned_manager_id = $data['assigned_manager_id'] ?? null;

        if (!$id) {
            http_response_code(400);
            jsonResponse(['error' => 'Collection ID is required']);
        }

        // Calculate total cost
        $total_cost = 0;
        foreach ($parts_list as $part) {
            $total_cost += ($part['quantity'] ?? 0) * ($part['price'] ?? 0);
        }

        $query = "UPDATE parts_collections SET parts_list = ?, total_cost = ?";
        $params = [json_encode($parts_list), $total_cost];

        if ($status !== null) {
            $query .= ", status = ?";
            $params[] = $status;
        }

        if ($assigned_manager_id !== null) {
            $query .= ", assigned_manager_id = ?";
            $params[] = $assigned_manager_id;
        }

        $query .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

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
    if ($action === 'get_parts_suggestions' && $method === 'GET') {
        $stmt = $pdo->query("SELECT DISTINCT JSON_EXTRACT(parts_list, '$[*].name') as part_names FROM parts_collections");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $suggestions = [];
        foreach ($results as $result) {
            $names = json_decode($result['part_names'], true);
            if (is_array($names)) {
                $suggestions = array_merge($suggestions, $names);
            }
        }
        
        $uniqueSuggestions = array_unique(array_filter($suggestions));
        sort($uniqueSuggestions);
        
        jsonResponse(['suggestions' => array_values($uniqueSuggestions)]);
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

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>