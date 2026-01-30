<?php
/**
 * Setup script for statuses table
 * Run this once to create the centralized status management system
 */

require_once 'config.php';

// Detect if running in browser or CLI
$isCli = php_sapi_name() === 'cli';
$br = $isCli ? "\n" : "<br>";

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>Setup Statuses</title></head><body><pre style='font-family: monospace; padding: 20px;'>";
}

try {
    $pdo = getDBConnection();
    
    // Create statuses table
    $sql = "CREATE TABLE IF NOT EXISTS `statuses` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `type` ENUM('case', 'repair') NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `color` VARCHAR(20) DEFAULT '#6B7280',
        `bg_color` VARCHAR(20) DEFAULT '#F3F4F6',
        `icon` VARCHAR(50) DEFAULT NULL,
        `sort_order` INT DEFAULT 0,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_type_name` (`type`, `name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "✅ statuses table created successfully!{$br}{$br}";
    
    // Insert default case statuses
    $caseStatuses = [
        ['New', '#3B82F6', '#DBEAFE', 1],
        ['Processing', '#F59E0B', '#FEF3C7', 2],
        ['Called', '#8B5CF6', '#EDE9FE', 3],
        ['Parts Ordered', '#EC4899', '#FCE7F3', 4],
        ['Parts Arrived', '#14B8A6', '#CCFBF1', 5],
        ['Scheduled', '#6366F1', '#E0E7FF', 6],
        ['Already in service', '#F97316', '#FFEDD5', 7],
        ['Completed', '#10B981', '#D1FAE5', 8],
        ['Issue', '#EF4444', '#FEE2E2', 9]
    ];
    
    $insertStmt = $pdo->prepare("
        INSERT INTO `statuses` (`type`, `name`, `color`, `bg_color`, `sort_order`) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE `sort_order` = VALUES(`sort_order`), `color` = VALUES(`color`), `bg_color` = VALUES(`bg_color`)
    ");
    
    echo "Inserting case statuses:{$br}";
    foreach ($caseStatuses as $status) {
        $insertStmt->execute(['case', $status[0], $status[1], $status[2], $status[3]]);
        echo "  ✓ {$status[0]}{$br}";
    }
    
    // Insert default repair statuses
    $repairStatuses = [
        ['წინასწარი შეფასება', '#3B82F6', '#DBEAFE', 1],
        ['მუშავდება', '#F59E0B', '#FEF3C7', 2],
        ['იღებება', '#8B5CF6', '#EDE9FE', 3],
        ['იშლება', '#EF4444', '#FEE2E2', 4],
        ['აწყობა', '#A855F7', '#F3E8FF', 5],
        ['თუნუქი', '#06B6D4', '#CFFAFE', 6],
        ['პლასტმასის აღდგენა', '#84CC16', '#ECFCCB', 7],
        ['პოლირება', '#EC4899', '#FCE7F3', 8],
        ['დაშლილი და გასული', '#10B981', '#D1FAE5', 9]
    ];
    
    echo "{$br}Inserting repair statuses:{$br}";
    foreach ($repairStatuses as $status) {
        $insertStmt->execute(['repair', $status[0], $status[1], $status[2], $status[3]]);
        echo "  ✓ {$status[0]}{$br}";
    }
    
    // Show final count
    $countStmt = $pdo->query("SELECT type, COUNT(*) as cnt FROM statuses GROUP BY type");
    echo "{$br}" . str_repeat("-", 40) . "{$br}";
    echo "Summary:{$br}";
    while ($row = $countStmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['type']} statuses: {$row['cnt']}{$br}";
    }
    
    echo "{$br}✅ Setup complete!{$br}";
    
} catch (Exception $e) {
    echo "❌ Error: " . htmlspecialchars($e->getMessage()) . "{$br}";
}

if (!$isCli) {
    echo "</pre></body></html>";
}
?>
