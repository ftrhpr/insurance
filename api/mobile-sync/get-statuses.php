<?php
define('API_ACCESS', true);
require_once 'config.php';

// Verify API key
verifyAPIKey();

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    sendResponse(false, null, 'Method not allowed', 405);
}

try {
    // Get database connection
    $pdo = getDBConnection();
    
    // Get optional type filter (case_status, repair_status, or null for all)
    $type = isset($_GET['type']) ? $_GET['type'] : null;
    
    // Build query
    $sql = "SELECT id, type, name, color, bg_color, icon, sort_order, is_active, created_at, updated_at 
            FROM statuses 
            WHERE is_active = 1";
    
    if ($type) {
        $sql .= " AND type = :type";
    }
    
    $sql .= " ORDER BY sort_order ASC, name ASC";
    
    $stmt = $pdo->prepare($sql);
    
    if ($type) {
        $stmt->execute([':type' => $type]);
    } else {
        $stmt->execute();
    }
    
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug logging
    error_log('Statuses query returned ' . count($statuses) . ' rows');
    
    // Group by type for easier consumption
    $groupedStatuses = [
        'case_status' => [],
        'repair_status' => []
    ];
    
    foreach ($statuses as $status) {
        $statusType = $status['type'];
        if (isset($groupedStatuses[$statusType])) {
            $groupedStatuses[$statusType][] = [
                'id' => intval($status['id']),
                'type' => $status['type'],
                'name' => $status['name'],
                'color' => $status['color'],
                'bgColor' => $status['bg_color'],
                'icon' => $status['icon'],
                'sortOrder' => intval($status['sort_order']),
                'isActive' => boolval($status['is_active']),
                'createdAt' => $status['created_at'],
                'updatedAt' => $status['updated_at']
            ];
        }
    }
    
    // Debug logging
    error_log('Grouped statuses - case_status: ' . count($groupedStatuses['case_status']) . ', repair_status: ' . count($groupedStatuses['repair_status']));
    
    sendResponse(true, [
        'statuses' => $groupedStatuses,
        'all' => $statuses,
        'count' => count($statuses)
    ]);
    
} catch (Exception $e) {
    error_log('Error fetching statuses: ' . $e->getMessage());
    sendResponse(false, null, 'Failed to fetch statuses: ' . $e->getMessage(), 500);
}
