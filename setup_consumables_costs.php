<?php
/**
 * Setup script for consumables_costs table
 * Run this once to create the table for tracking monthly consumables costs per technician
 */

require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // Create consumables_costs table
    $sql = "CREATE TABLE IF NOT EXISTS consumables_costs (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `technician_name` VARCHAR(255) NOT NULL,
        `year_month` VARCHAR(7) NOT NULL,
        `cost` DECIMAL(10, 2) NOT NULL DEFAULT 0,
        `notes` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_tech_month` (`technician_name`, `year_month`)
    )";
    
    $pdo->exec($sql);
    
    echo "✅ consumables_costs table created successfully!\n";
    
    // Verify table exists
    $result = $pdo->query("SHOW TABLES LIKE 'consumables_costs'");
    if ($result->rowCount() > 0) {
        echo "✅ Table verified.\n";
        
        // Show table structure
        $columns = $pdo->query("DESCRIBE consumables_costs");
        echo "\nTable structure:\n";
        echo str_repeat("-", 60) . "\n";
        foreach ($columns as $col) {
            echo sprintf("%-20s %-20s %s\n", $col['Field'], $col['Type'], $col['Key'] ? "({$col['Key']})" : "");
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
