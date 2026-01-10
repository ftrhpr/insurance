<?php
define('API_ACCESS', true);
require_once 'config.php';

// Verify API key
verifyAPIKey();

try {
    $pdo = getDBConnection();
    
    // Get recent transfers with potential image data
    $stmt = $pdo->query("
        SELECT id, name, plate, vehicle_make, vehicle_model, 
               case_images, operatorComment, 
               LEFT(COALESCE(case_images, 'NULL'), 200) as case_images_preview,
               created_at
        FROM transfers 
        ORDER BY id DESC 
        LIMIT 20
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
                'raw_value_preview' => substr($t['case_images'], 0, 100)
            ];
        }
    }
    
    // Check if case_images column exists
    $columnCheck = $pdo->query("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'transfers' 
        AND COLUMN_NAME = 'case_images'
    ")->fetch(PDO::FETCH_ASSOC);
    
    sendResponse(true, [
        'message' => 'Debug info for case images',
        'column_exists' => !empty($columnCheck),
        'column_info' => $columnCheck ?: 'Column not found - run migration!',
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
        'expected_mobile_app_format' => [
            'note' => 'Mobile app should send images in one of these fields:',
            'fields' => ['images', 'photos', 'imageUrls', 'photoUrls', 'caseImages', 'vehicleImages', 'damageImages', 'attachments'],
            'example' => [
                'images' => [
                    'https://firebasestorage.googleapis.com/v0/b/autobodyestimator.firebasestorage.app/o/images%2Fexample.jpg?alt=media',
                    'https://firebasestorage.googleapis.com/v0/b/autobodyestimator.firebasestorage.app/o/images%2Fexample2.jpg?alt=media'
                ]
            ]
        ]
    ]);
    
} catch (Exception $e) {
    sendResponse(false, null, 'Debug failed: ' . $e->getMessage(), 500);
}
?>
