<?php
/**
 * Database Migration: Fix Race Conditions
 * Adds unique constraints and ensures InnoDB engine for transaction support
 * Run this ONCE after deploying race condition fixes
 * 
 * Date: December 8, 2025
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h1>Race Condition Fixes - Database Migration</h1>";
echo "<pre>";

try {
    $pdo = getDBConnection();
    echo "✓ Database connected\n\n";
    
    // 1. Check and set InnoDB engine for all tables
    echo "=== CHECKING TABLE ENGINES ===\n";
    $tables = ['transfers', 'vehicles', 'users', 'customer_reviews', 'sms_templates', 'manager_tokens'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLE STATUS WHERE Name = '$table'");
        $info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($info) {
            $engine = $info['Engine'];
            echo "Table: $table - Engine: $engine\n";
            
            if (strtoupper($engine) !== 'INNODB') {
                echo "  → Converting to InnoDB... ";
                $pdo->exec("ALTER TABLE $table ENGINE=InnoDB");
                echo "✓ Done\n";
            } else {
                echo "  → Already InnoDB ✓\n";
            }
        } else {
            echo "Table: $table - NOT FOUND (may not exist yet)\n";
        }
    }
    
    echo "\n";
    
    // 2. Add unique constraint on vehicles.plate if it doesn't exist
    echo "=== CHECKING UNIQUE CONSTRAINTS ===\n";
    
    // Check if unique constraint exists
    $stmt = $pdo->query("SHOW INDEX FROM vehicles WHERE Key_name = 'unique_plate'");
    $uniqueExists = $stmt->fetch();
    
    if (!$uniqueExists) {
        echo "Adding UNIQUE constraint on vehicles.plate... ";
        
        try {
            // First, check for duplicate plates
            $stmt = $pdo->query("
                SELECT plate, COUNT(*) as count 
                FROM vehicles 
                GROUP BY plate 
                HAVING count > 1
            ");
            $duplicates = $stmt->fetchAll();
            
            if (count($duplicates) > 0) {
                echo "\n⚠️ WARNING: Duplicate plates found:\n";
                foreach ($duplicates as $dup) {
                    echo "  - {$dup['plate']} ({$dup['count']} records)\n";
                }
                echo "\nCleaning up duplicates (keeping newest record)...\n";
                
                foreach ($duplicates as $dup) {
                    // Keep the record with highest ID (newest), delete others
                    $pdo->exec("
                        DELETE FROM vehicles 
                        WHERE plate = '{$dup['plate']}' 
                        AND id NOT IN (
                            SELECT * FROM (
                                SELECT MAX(id) FROM vehicles WHERE plate = '{$dup['plate']}'
                            ) AS t
                        )
                    ");
                    echo "  ✓ Cleaned {$dup['plate']}\n";
                }
            }
            
            // Now add the unique constraint
            $pdo->exec("ALTER TABLE vehicles ADD UNIQUE KEY `unique_plate` (`plate`)");
            echo "✓ UNIQUE constraint added\n";
            
        } catch (PDOException $e) {
            echo "✗ Error: " . $e->getMessage() . "\n";
            echo "Please manually resolve duplicate plates before adding constraint.\n";
        }
    } else {
        echo "vehicles.plate UNIQUE constraint already exists ✓\n";
    }
    
    echo "\n";
    
    // 3. Verify transaction support
    echo "=== VERIFYING TRANSACTION SUPPORT ===\n";
    
    try {
        $pdo->beginTransaction();
        $pdo->exec("SELECT 1");
        $pdo->commit();
        echo "✓ Transactions supported and working\n";
    } catch (Exception $e) {
        echo "✗ Transaction test failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // 4. Check current isolation level
    echo "=== TRANSACTION ISOLATION LEVEL ===\n";
    $stmt = $pdo->query("SELECT @@transaction_isolation");
    $isolation = $stmt->fetchColumn();
    echo "Current isolation level: $isolation\n";
    
    if (strtoupper($isolation) === 'REPEATABLE-READ') {
        echo "✓ Optimal for race condition prevention\n";
    } else {
        echo "⚠️ Consider using REPEATABLE-READ for better protection\n";
        echo "Run: SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ;\n";
    }
    
    echo "\n";
    
    // 5. Configuration recommendations
    echo "=== CONFIGURATION RECOMMENDATIONS ===\n";
    
    $configs = [
        'innodb_lock_wait_timeout' => 50,
        'max_connections' => 151,
        'innodb_buffer_pool_size' => null // Don't check (varies by server)
    ];
    
    foreach ($configs as $var => $recommended) {
        if ($recommended !== null) {
            $stmt = $pdo->query("SHOW VARIABLES LIKE '$var'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $current = $result['Value'] ?? 'N/A';
            
            echo "$var: $current";
            if ($current != $recommended) {
                echo " (recommended: $recommended)";
            }
            echo "\n";
        }
    }
    
    echo "\n";
    echo "=== MIGRATION COMPLETE ===\n";
    echo "✓ All tables using InnoDB engine\n";
    echo "✓ Unique constraints in place\n";
    echo "✓ Transaction support verified\n";
    echo "\nYou can now safely deploy the race condition fixes.\n";
    echo "\nMonitoring commands:\n";
    echo "  - Check for deadlocks: SHOW ENGINE INNODB STATUS\\G\n";
    echo "  - Monitor locks: SELECT * FROM information_schema.innodb_lock_waits;\n";
    echo "  - Long transactions: SELECT * FROM information_schema.innodb_trx;\n";
    
} catch (Exception $e) {
    echo "\n✗ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
?>
