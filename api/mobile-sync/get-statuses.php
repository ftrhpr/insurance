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
    
    // Check if statuses table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'statuses'");
    if ($tableCheck->rowCount() === 0) {
        // Table doesn't exist - return fallback statuses
        error_log('Statuses table does not exist - returning fallback statuses');
        
        $fallbackStatuses = [
            'case_status' => [
                ['id' => 1, 'type' => 'case', 'name' => 'New', 'color' => '#3B82F6', 'bgColor' => '#DBEAFE', 'icon' => null, 'sortOrder' => 1, 'isActive' => true, 'createdAt' => null, 'updatedAt' => null],
                ['id' => 2, 'type' => 'case', 'name' => 'Processing', 'color' => '#F59E0B', 'bgColor' => '#FEF3C7', 'icon' => null, 'sortOrder' => 2, 'isActive' => true, 'createdAt' => null, 'updatedAt' => null],
                ['id' => 3, 'type' => 'case', 'name' => 'Called', 'color' => '#8B5CF6', 'bgColor' => '#EDE9FE', 'icon' => null, 'sortOrder' => 3, 'isActive' => true, 'createdAt' => null, 'updatedAt' => null],
                ['id' => 4, 'type' => 'case', 'name' => 'Parts Ordered', 'color' => '#EC4899', 'bgColor' => '#FCE7F3', 'icon' => null, 'sortOrder' => 4, 'isActive' => true, 'createdAt' => null, 'updatedAt' => null],
                ['id' => 5, 'type' => 'case', 'name' => 'Parts Arrived', 'color' => '#14B8A6', 'bgColor' => '#CCFBF1', 'icon' => null, 'sortOrder' => 5, 'isActive' => true, 'createdAt' => null, 'updatedAt' => null],
                ['id' => 6, 'type' => 'case', 'name' => 'Scheduled', 'color' => '#6366F1', 'bgColor' => '#E0E7FF', 'icon' => null, 'sortOrder' => 6, 'isActive' => true, 'createdAt' => null, 'updatedAt' => null],
                ['id' => 7, 'type' => 'case', 'name' => 'Already in service', 'color' => '#F97316', 'bgColor' => '#FFEDD5', 'icon' => null, 'sortOrder' => 7, 'isActive' => true, 'createdAt' => null, 'updatedAt' => null],
                ['id' => 8, 'type' => 'case', 'name' => 'Completed', 'color' => '#10B981', 'bgColor' => '#D1FAE5', 'icon' => null, 'sortOrder' => 8, 'isActive' => true, 'createdAt' => null, 'updatedAt' => null],
                ['id' => 9, 'type' => 'case', 'name' => 'Issue', 'color' => '#EF4444', 'bgColor' => '#FEE2E2', 'icon' => null, 'sortOrder' => 9, 'isActive' => true, 'createdAt' => null, 'updatedAt' => null]
            ],
            'repair_status' => [
                ['id' => 10, 'type' => 'repair', 'name' => 'წიანსწარი შეფასება', 'color' => '#3B82F6', 'bgColor' => '#DBEAFE', 'icon' => null, 'sortOrder' => 1, 'isActive' => true, 'createdAt' => null, 'updatedAt' => null],
                ['id' => 11, 'type' => 'repair', 'name' => 'მუშავდება', 'color' => '#F59E0B', 'bgColor' => '#FEF3C7', 'icon' => null, 'sortOrder' => 2, 'isActive' => true, 'createdAt' => null, 'updatedAt' => null],
                ['id' => 12, 'type' => 'repair', 'name' => 'იღებება', 'color' => '#8B5CF6', 'bgColor' => '#EDE9FE', 'icon' => null, 'sortOrder' => 3, 'isActive' => true, 'createdAt' => null, 'updatedAt' => null],
                ['id' => 13, 'type' => 'repair', 'name' => 'იშლება', 'color' => '#EF4444', 'bgColor' => '#FEE2E2', 'icon' => null, 'sortOrder' => 4, 'isActive' => true, 'createdAt' => null, 'updatedAt' => null],
                ['id' => 14, 'type' => 'repair', 'name' => 'აწყობა', 'color' => '#A855F7', 'bgColor' => '#F3E8FF', 'icon' => null, 'sortOrder' => 5, 'isActive' => true, 'createdAt' => null, 'updatedAt' => null],
                ['id' => 15, 'type' => 'repair', 'name' => 'თუნუქი', 'color' => '#06B6D4', 'bgColor' => '#CFFAFE', 'icon' => null, 'sortOrder' => 6, 'isActive' => true, 'createdAt' => null, 'updatedAt' => null],
                ['id' => 16, 'type' => 'repair', 'name' => 'პლასტმასის აღდგენა', 'color' => '#84CC16', 'bgColor' => '#ECFCCB', 'icon' => null, 'sortOrder' => 7, 'isActive' => true, 'createdAt' => null, 'updatedAt' => null],
                ['id' => 17, 'type' => 'repair', 'name' => 'პოლირება', 'color' => '#EC4899', 'bgColor' => '#FCE7F3', 'icon' => null, 'sortOrder' => 8, 'isActive' => true, 'createdAt' => null, 'updatedAt' => null],
                ['id' => 18, 'type' => 'repair', 'name' => 'დაშლილი და გასული', 'color' => '#10B981', 'bgColor' => '#D1FAE5', 'icon' => null, 'sortOrder' => 9, 'isActive' => true, 'createdAt' => null, 'updatedAt' => null]
            ]
        ];
        
        sendResponse(true, [
            'statuses' => $fallbackStatuses,
            'all' => array_merge($fallbackStatuses['case_status'], $fallbackStatuses['repair_status']),
            'count' => 18,
            'source' => 'fallback'
        ]);
    }
    
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
        
        // Map database type values to expected keys
        $mappedType = null;
        if ($statusType === 'case' || $statusType === 'case_status') {
            $mappedType = 'case_status';
        } elseif ($statusType === 'repair' || $statusType === 'repair_status') {
            $mappedType = 'repair_status';
        }
        
        if ($mappedType) {
            $groupedStatuses[$mappedType][] = [
                'id' => intval($status['id']),
                'type' => $statusType,
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
