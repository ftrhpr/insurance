<?php
// roapp_webhook.php - Handles RO App webhook for order status changes and syncs with OTOMOTORS workflow
// Place this file on your server and register its URL as a webhook in RO App (Settings > API)

require_once 'config.php';

// --- CONFIG ---
$ROAPP_API_KEY = '568f4ff46dd64c5ea9e18039f1915230';
$ROAPP_API_URL = 'https://api.roapp.io';
$OTOEXPRESS_DONE_STAGE = 'Completed'; // The local workflow stage to set

// --- Helper: Fetch all RO App order statuses and find the 'Done' status ID ---
function get_roapp_done_status_id($apiKey) {
    $ch = curl_init("https://api.roapp.io/statuses/orders");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $apiKey"
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!$resp) return null;
    $data = json_decode($resp, true);
    if (!is_array($data)) return null;
    foreach ($data as $status) {
        if (isset($status['name']) && strtolower($status['name']) === 'done') {
            return $status['id'];
        }
    }
    return null;
}

// --- Helper: Normalize plate (remove spaces/hyphens, uppercase) ---
function normalize_plate($plate) {
    return strtoupper(str_replace([' ', '-'], '', $plate));
}

// --- Main webhook handler ---

$input = file_get_contents('php://input');
// Log all incoming payloads for debugging
file_put_contents(__DIR__ . '/roapp_webhook.log', date('c') . "\n" . $input . "\n---\n", FILE_APPEND);

// Webhook signature verification
$secret = '_x-HQ9WhfeCrMf6_M1kCi';
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$payloadData = json_decode($input, true);
$webhookId = $payloadData['id'] ?? '';
$expectedSignature = hash('sha256', $webhookId . $secret);

if (!$signature || $signature !== $expectedSignature) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$data = $payloadData;

if (!$data || !isset($data['event_name']) || $data['event_name'] !== 'Order.Status.Changed') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid event']);
    exit;
}

$plate = $data['metadata']['order']['name'] ?? null;
$newStatusId = $data['metadata']['new']['id'] ?? null;
if (!$plate || !$newStatusId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing plate or status']);
    exit;
}

// Get the RO App 'Done' status ID (cache this in production!)
$doneStatusId = get_roapp_done_status_id($ROAPP_API_KEY);
if (!$doneStatusId) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not determine Done status ID']);
    exit;
}

if ($newStatusId != $doneStatusId) {
    // Not a transition to Done, ignore
    echo json_encode(['ok' => 'Not Done status, no action']);
    exit;
}

// --- Find and update in otoexpress DB ---
try {
    $pdo = getDBConnection();
    $normPlate = normalize_plate($plate);
    $stmt = $pdo->prepare("SELECT id, plate, status FROM transfers WHERE REPLACE(REPLACE(UPPER(plate),' ',''),'-','') = ? LIMIT 1");
    $stmt->execute([$normPlate]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Plate not found in otoexpress']);
        exit;
    }
    // Update status to Completed
    $update = $pdo->prepare("UPDATE transfers SET status = ? WHERE id = ?");
    $update->execute([$OTOEXPRESS_DONE_STAGE, $row['id']]);
    echo json_encode(['ok' => 'Status updated', 'plate' => $row['plate'], 'id' => $row['id']]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
