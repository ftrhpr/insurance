<?php
/**
 * Get Mechanics API
 * Fetches list of mechanics from the database
 */

// Define API_ACCESS before including config.php
define('API_ACCESS', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

// Verify API key
$headers = getallheaders();
$apiKey = $headers['X-API-Key'] ?? $headers['x-api-key'] ?? '';

if ($apiKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Query to get users with technician role
    $stmt = $pdo->query("
        SELECT id, full_name as name 
        FROM users 
        WHERE role = 'technician' 
        ORDER BY full_name ASC
    ");
    
    $mechanics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'mechanics' => $mechanics,
            'count' => count($mechanics)
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Get mechanics error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
