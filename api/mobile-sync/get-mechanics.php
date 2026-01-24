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
    
    // Query to get unique mechanics from transfers table
    // This gets all distinct assigned_mechanic values that are not null or empty
    $stmt = $pdo->query("
        SELECT DISTINCT assigned_mechanic as name 
        FROM transfers 
        WHERE assigned_mechanic IS NOT NULL 
        AND assigned_mechanic != '' 
        ORDER BY assigned_mechanic ASC
    ");
    
    $mechanics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response with id (index) and name
    $formattedMechanics = array_map(function($mechanic, $index) {
        return [
            'id' => $index + 1,
            'name' => $mechanic['name']
        ];
    }, $mechanics, array_keys($mechanics));
    
    echo json_encode([
        'success' => true,
        'data' => [
            'mechanics' => $formattedMechanics,
            'count' => count($formattedMechanics)
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
