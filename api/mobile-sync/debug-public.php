<?php
// Public debug endpoint - no API key required
// DELETE THIS FILE AFTER DEBUGGING

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

// Database configuration (same as config.php)
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
    
    // Check if case_images column exists
    $columnCheck = $pdo->query("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'transfers' 
        AND COLUMN_NAME = 'case_images'
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Get recent transfers
    $stmt = $pdo->query("
        SELECT id, name, plate, vehicle_make, vehicle_model, 
               case_images, operatorComment, created_at
        FROM transfers 
        ORDER BY id DESC 
        LIMIT 15
    ");
    $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count transfers with images
    $withImages = 0;
    $imageDetails = [];
    foreach ($transfers as $t) {
        if (!empty($t['case_images']) && $t['case_images'] !== 'null') {
            $withImages++;
            $decoded = json_decode($t['case_images'], true);
            $imageDetails[] = [
                'id' => $t['id'],
                'name' => $t['name'],
                'image_count' => is_array($decoded) ? count($decoded) : 0,
                'images' => $decoded
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'column_exists' => !empty($columnCheck),
        'column_info' => $columnCheck ?: 'Column NOT FOUND - run: ALTER TABLE transfers ADD COLUMN case_images TEXT DEFAULT NULL',
        'total_transfers' => count($transfers),
        'transfers_with_images' => $withImages,
        'image_details' => $imageDetails,
        'recent_transfers' => array_map(function($t) {
            return [
                'id' => $t['id'],
                'name' => $t['name'],
                'plate' => $t['plate'],
                'vehicle' => trim(($t['vehicle_make'] ?? '') . ' ' . ($t['vehicle_model'] ?? '')),
                'has_images' => !empty($t['case_images']) && $t['case_images'] !== 'null',
                'from_mobile' => strpos($t['operatorComment'] ?? '', 'mobile app') !== false,
                'created' => $t['created_at']
            ];
        }, $transfers),
        'instructions' => [
            'step1' => 'If column_exists is false, run the SQL migration',
            'step2' => 'Your React Native app should send images in the "images" field',
            'step3' => 'Images should be an array of Firebase Storage URLs',
            'example_payload' => [
                'customerPhone' => '555123456',
                'totalPrice' => 500,
                'images' => ['https://firebasestorage.googleapis.com/.../image1.jpg']
            ]
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
