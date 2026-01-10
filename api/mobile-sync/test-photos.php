<?php
/**
 * Test Photos Endpoint
 * Tests photo saving and retrieval
 * DELETE THIS FILE AFTER DEBUGGING
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'otoexpre_userdb');
define('DB_USER', 'otoexpre_userdb');
define('DB_PASS', 'p52DSsthB}=0AeZ#');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $result = [
        'timestamp' => date('Y-m-d H:i:s'),
        'tests' => []
    ];
    
    // Test 1: Check if case_images column exists
    $columnCheck = $pdo->query("
        SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = 'otoexpre_userdb'
        AND TABLE_NAME = 'transfers' 
        AND COLUMN_NAME = 'case_images'
    ")->fetch(PDO::FETCH_ASSOC);
    
    $result['tests']['column_exists'] = [
        'pass' => !empty($columnCheck),
        'details' => $columnCheck ?: 'Column not found'
    ];
    
    // Test 2: Get recent transfers and their case_images status
    $stmt = $pdo->query("
        SELECT id, name, plate, 
               case_images,
               LENGTH(case_images) as image_data_length,
               created_at
        FROM transfers 
        ORDER BY id DESC 
        LIMIT 10
    ");
    $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $transfersWithImages = [];
    $transfersWithoutImages = [];
    
    foreach ($transfers as $t) {
        $hasImages = !empty($t['case_images']) && $t['case_images'] !== 'null' && $t['case_images'] !== '[]';
        $decoded = json_decode($t['case_images'], true);
        $imageCount = is_array($decoded) ? count($decoded) : 0;
        
        $info = [
            'id' => $t['id'],
            'name' => $t['name'],
            'plate' => $t['plate'],
            'has_images' => $hasImages,
            'image_count' => $imageCount,
            'raw_data_length' => $t['image_data_length'],
            'first_url_preview' => $imageCount > 0 ? substr($decoded[0], 0, 80) . '...' : null
        ];
        
        if ($hasImages) {
            $transfersWithImages[] = $info;
        } else {
            $transfersWithoutImages[] = $info;
        }
    }
    
    $result['tests']['transfers_with_images'] = [
        'count' => count($transfersWithImages),
        'items' => $transfersWithImages
    ];
    
    $result['tests']['transfers_without_images'] = [
        'count' => count($transfersWithoutImages),
        'items' => array_slice($transfersWithoutImages, 0, 5) // Show first 5
    ];
    
    // Test 3: Check error_log for recent image-related entries (if accessible)
    $result['tests']['api_logs'] = 'Check server error_log for entries containing "Images found" or "Images transformed"';
    
    // Test 4: Instructions
    $result['instructions'] = [
        '1. Update your React Native app to include photos array in payload',
        '2. In cpanelService.js syncInvoiceToCPanel function, add:',
        '   photos: invoiceData.photos || [],',
        '3. Sync an invoice that has photos',
        '4. Refresh this endpoint to see if images appear'
    ];
    
    // Test 5: Sample test - manually insert a test image
    if (isset($_GET['test_insert']) && $_GET['test_insert'] === 'yes') {
        // Find latest transfer
        $latest = $pdo->query("SELECT id FROM transfers ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_COLUMN);
        if ($latest) {
            $testImages = json_encode([
                'https://firebasestorage.googleapis.com/v0/b/autobodyestimator.appspot.com/test1.jpg',
                'https://firebasestorage.googleapis.com/v0/b/autobodyestimator.appspot.com/test2.jpg'
            ]);
            $stmt = $pdo->prepare("UPDATE transfers SET case_images = ? WHERE id = ?");
            $stmt->execute([$testImages, $latest]);
            $result['tests']['manual_insert'] = [
                'success' => true,
                'transfer_id' => $latest,
                'message' => "Test images inserted. Check edit_case.php?id=$latest"
            ];
        }
    } else {
        $result['tests']['manual_insert'] = 'Add ?test_insert=yes to URL to test manual insert';
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
