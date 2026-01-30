<?php
/**
 * Migration: Convert status text fields to status_id references
 * 
 * This script:
 * 1. Adds status_id and repair_status_id columns to transfers table
 * 2. Migrates existing text values to IDs
 * 3. Keeps old columns for backward compatibility during transition
 */

require_once 'config.php';

$isCli = php_sapi_name() === 'cli';
$br = $isCli ? "\n" : "<br>";

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>Migrate Status to ID</title></head><body><pre style='font-family: monospace; padding: 20px;'>";
}

try {
    $pdo = getDBConnection();
    
    echo "=== Status ID Migration ==={$br}{$br}";
    
    // Step 1: Check if statuses table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'statuses'");
    if ($tableCheck->rowCount() === 0) {
        echo "❌ Error: 'statuses' table does not exist. Please run setup_statuses.php first.{$br}";
        exit(1);
    }
    echo "✅ statuses table exists{$br}";
    
    // Step 2: Add status_id column if not exists
    $columns = $pdo->query("SHOW COLUMNS FROM `transfers` LIKE 'status_id'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE `transfers` ADD COLUMN `status_id` INT DEFAULT NULL AFTER `status`");
        echo "✅ Added status_id column{$br}";
    } else {
        echo "ℹ️ status_id column already exists{$br}";
    }
    
    // Step 3: Add repair_status_id column if not exists
    $columns = $pdo->query("SHOW COLUMNS FROM `transfers` LIKE 'repair_status_id'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE `transfers` ADD COLUMN `repair_status_id` INT DEFAULT NULL AFTER `repair_status`");
        echo "✅ Added repair_status_id column{$br}";
    } else {
        echo "ℹ️ repair_status_id column already exists{$br}";
    }
    
    // Step 4: Load all statuses
    $statusStmt = $pdo->query("SELECT * FROM `statuses`");
    $statuses = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build lookup maps
    $caseStatusMap = [];
    $repairStatusMap = [];
    foreach ($statuses as $s) {
        if ($s['type'] === 'case') {
            $caseStatusMap[strtolower(trim($s['name']))] = $s['id'];
        } else {
            $repairStatusMap[strtolower(trim($s['name']))] = $s['id'];
        }
    }
    
    echo "{$br}Loaded " . count($caseStatusMap) . " case statuses and " . count($repairStatusMap) . " repair statuses{$br}{$br}";
    
    // Step 5: Migrate existing status text to status_id
    echo "Migrating case statuses...{$br}";
    $updateStmt = $pdo->prepare("UPDATE `transfers` SET `status_id` = ? WHERE LOWER(TRIM(`status`)) = ? AND (`status_id` IS NULL OR `status_id` = 0)");
    $migratedCase = 0;
    foreach ($caseStatusMap as $name => $id) {
        $updateStmt->execute([$id, $name]);
        $count = $updateStmt->rowCount();
        if ($count > 0) {
            echo "  ✓ '{$name}' → ID {$id} ({$count} records){$br}";
            $migratedCase += $count;
        }
    }
    echo "Total case statuses migrated: {$migratedCase}{$br}{$br}";
    
    // Step 6: Migrate existing repair_status text to repair_status_id
    echo "Migrating repair statuses...{$br}";
    $updateStmt = $pdo->prepare("UPDATE `transfers` SET `repair_status_id` = ? WHERE LOWER(TRIM(`repair_status`)) = ? AND (`repair_status_id` IS NULL OR `repair_status_id` = 0)");
    $migratedRepair = 0;
    foreach ($repairStatusMap as $name => $id) {
        $updateStmt->execute([$id, $name]);
        $count = $updateStmt->rowCount();
        if ($count > 0) {
            echo "  ✓ '{$name}' → ID {$id} ({$count} records){$br}";
            $migratedRepair += $count;
        }
    }
    echo "Total repair statuses migrated: {$migratedRepair}{$br}{$br}";
    
    // Step 7: Report unmigrated records
    $unmigrated = $pdo->query("SELECT COUNT(*) FROM `transfers` WHERE `status` IS NOT NULL AND `status` != '' AND (`status_id` IS NULL OR `status_id` = 0)")->fetchColumn();
    if ($unmigrated > 0) {
        echo "⚠️ Warning: {$unmigrated} records have status text that didn't match any defined status{$br}";
        
        // Show what values couldn't be matched
        $unmatched = $pdo->query("SELECT DISTINCT `status` FROM `transfers` WHERE `status` IS NOT NULL AND `status` != '' AND (`status_id` IS NULL OR `status_id` = 0)")->fetchAll(PDO::FETCH_COLUMN);
        echo "   Unmatched values: " . implode(', ', $unmatched) . "{$br}";
    }
    
    $unmigratedRepair = $pdo->query("SELECT COUNT(*) FROM `transfers` WHERE `repair_status` IS NOT NULL AND `repair_status` != '' AND (`repair_status_id` IS NULL OR `repair_status_id` = 0)")->fetchColumn();
    if ($unmigratedRepair > 0) {
        echo "⚠️ Warning: {$unmigratedRepair} records have repair_status text that didn't match any defined status{$br}";
        
        $unmatched = $pdo->query("SELECT DISTINCT `repair_status` FROM `transfers` WHERE `repair_status` IS NOT NULL AND `repair_status` != '' AND (`repair_status_id` IS NULL OR `repair_status_id` = 0)")->fetchAll(PDO::FETCH_COLUMN);
        echo "   Unmatched values: " . implode(', ', $unmatched) . "{$br}";
    }
    
    // Step 8: Add indexes for performance
    try {
        $pdo->exec("CREATE INDEX `idx_transfers_status_id` ON `transfers` (`status_id`)");
        echo "✅ Added index on status_id{$br}";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "ℹ️ Index on status_id already exists{$br}";
        }
    }
    
    try {
        $pdo->exec("CREATE INDEX `idx_transfers_repair_status_id` ON `transfers` (`repair_status_id`)");
        echo "✅ Added index on repair_status_id{$br}";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "ℹ️ Index on repair_status_id already exists{$br}";
        }
    }
    
    echo "{$br}=== Migration Complete ==={$br}";
    echo "The old 'status' and 'repair_status' columns are kept for backward compatibility.{$br}";
    echo "The system will now use status_id and repair_status_id.{$br}";
    
} catch (Exception $e) {
    echo "❌ Error: " . htmlspecialchars($e->getMessage()) . "{$br}";
}

if (!$isCli) {
    echo "</pre></body></html>";
}
?>
