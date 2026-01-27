<?php
/**
 * Get Statuses API
 * Fetches case statuses and repair statuses from the database
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
    
    // Check if tables exist
    $caseStatuses = [];
    $repairStatuses = [];
    
    // Try to get case statuses
    try {
        $stmt = $pdo->query("SELECT * FROM case_statuses WHERE is_active = 1 ORDER BY sort_order ASC");
        $caseStatuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table doesn't exist yet - return default statuses
        $caseStatuses = [
            ['id' => 1, 'slug' => 'New', 'name' => 'New', 'name_ka' => 'ახალი', 'name_en' => 'New', 'color' => 'blue', 'icon' => 'file-plus-2', 'is_default' => 1],
            ['id' => 2, 'slug' => 'Processing', 'name' => 'Processing', 'name_ka' => 'მუშავდება', 'name_en' => 'Processing', 'color' => 'yellow', 'icon' => 'loader-circle', 'is_default' => 0],
            ['id' => 3, 'slug' => 'Called', 'name' => 'Called', 'name_ka' => 'დაკავშირებული', 'name_en' => 'Contacted', 'color' => 'purple', 'icon' => 'phone', 'is_default' => 0],
            ['id' => 4, 'slug' => 'Parts Ordered', 'name' => 'Parts Ordered', 'name_ka' => 'ნაწილები შეკვეთილია', 'name_en' => 'Parts Ordered', 'color' => 'orange', 'icon' => 'box-select', 'is_default' => 0],
            ['id' => 5, 'slug' => 'Parts Arrived', 'name' => 'Parts Arrived', 'name_ka' => 'ნაწილები მოვიდა', 'name_en' => 'Parts Arrived', 'color' => 'teal', 'icon' => 'package-check', 'is_default' => 0],
            ['id' => 6, 'slug' => 'Scheduled', 'name' => 'Scheduled', 'name_ka' => 'დაგეგმილი', 'name_en' => 'Scheduled', 'color' => 'amber', 'icon' => 'calendar-days', 'is_default' => 0],
            ['id' => 7, 'slug' => 'Already in service', 'name' => 'Already in service', 'name_ka' => 'სერვისზეა', 'name_en' => 'In Service', 'color' => 'indigo', 'icon' => 'wrench', 'is_default' => 0],
            ['id' => 8, 'slug' => 'Completed', 'name' => 'Completed', 'name_ka' => 'დასრულებული', 'name_en' => 'Completed', 'color' => 'green', 'icon' => 'check-circle-2', 'is_default' => 0, 'is_final' => 1],
            ['id' => 9, 'slug' => 'Issue', 'name' => 'Issue', 'name_ka' => 'პრობლემა', 'name_en' => 'Issue', 'color' => 'red', 'icon' => 'alert-triangle', 'is_default' => 0]
        ];
    }
    
    // Try to get repair statuses
    try {
        $stmt = $pdo->query("SELECT * FROM repair_statuses WHERE is_active = 1 ORDER BY sort_order ASC");
        $repairStatuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table doesn't exist yet - return default statuses
        $repairStatuses = [
            ['id' => 1, 'slug' => 'wianswari-shefaseba', 'name' => 'წიანსწარი შეფასება', 'name_ka' => 'წიანსწარი შეფასება', 'name_en' => 'Preliminary Assessment', 'color' => 'blue', 'icon' => 'search'],
            ['id' => 2, 'slug' => 'mushavdeba', 'name' => 'მუშავდება', 'name_ka' => 'მუშავდება', 'name_en' => 'In Progress', 'color' => 'yellow', 'icon' => 'loader'],
            ['id' => 3, 'slug' => 'ighebeba', 'name' => 'იღებება', 'name_ka' => 'იღებება', 'name_en' => 'Receiving', 'color' => 'orange', 'icon' => 'download'],
            ['id' => 4, 'slug' => 'ishleba', 'name' => 'იშლება', 'name_ka' => 'იშლება', 'name_en' => 'Disassembly', 'color' => 'red', 'icon' => 'scissors'],
            ['id' => 5, 'slug' => 'awqoba', 'name' => 'აწყობა', 'name_ka' => 'აწყობა', 'name_en' => 'Assembly', 'color' => 'purple', 'icon' => 'wrench'],
            ['id' => 6, 'slug' => 'tunuqi', 'name' => 'თუნუქი', 'name_ka' => 'თუნუქი', 'name_en' => 'Body Work', 'color' => 'pink', 'icon' => 'hammer'],
            ['id' => 7, 'slug' => 'plastmasis-aghdgena', 'name' => 'პლასტმასის აღდგენა', 'name_ka' => 'პლასტმასის აღდგენა', 'name_en' => 'Plastic Restoration', 'color' => 'indigo', 'icon' => 'shapes'],
            ['id' => 8, 'slug' => 'polireba', 'name' => 'პოლირება', 'name_ka' => 'პოლირება', 'name_en' => 'Polishing', 'color' => 'teal', 'icon' => 'sparkles'],
            ['id' => 9, 'slug' => 'dashlili-da-gasuli', 'name' => 'დაშლილი და გასული', 'name_ka' => 'დაშლილი და გასული', 'name_en' => 'Completed & Released', 'color' => 'green', 'icon' => 'check-circle']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'case_statuses' => $caseStatuses,
            'repair_statuses' => $repairStatuses
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Get statuses error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
