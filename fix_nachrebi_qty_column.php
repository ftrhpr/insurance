<?php
/**
 * Fix nachrebi_qty column - change from INT to DECIMAL(10,2)
 * Run this file to update the existing column
 */

require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully.\n";
    
    // Check column type
    $checkStmt = $pdo->prepare("
        SELECT DATA_TYPE, COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = ? 
        AND TABLE_NAME = 'transfers' 
        AND COLUMN_NAME = 'nachrebi_qty'
    ");
    $checkStmt->execute([DB_NAME]);
    $columnInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$columnInfo) {
        echo "Column 'nachrebi_qty' does not exist. Adding it...\n";
        $sql = "ALTER TABLE transfers ADD COLUMN nachrebi_qty DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Pieces quantity (ნაჭრების რაოდენობა)'";
        $pdo->exec($sql);
        echo "Successfully added 'nachrebi_qty' column as DECIMAL(10,2).\n";
    } else {
        echo "Current column type: {$columnInfo['COLUMN_TYPE']}\n";
        
        if (strpos(strtolower($columnInfo['DATA_TYPE']), 'int') !== false) {
            echo "Column is INT, changing to DECIMAL(10,2)...\n";
            $sql = "ALTER TABLE transfers MODIFY COLUMN nachrebi_qty DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Pieces quantity (ნაჭრების რაოდენობა)'";
            $pdo->exec($sql);
            echo "Successfully changed 'nachrebi_qty' column to DECIMAL(10,2).\n";
        } else {
            echo "Column is already DECIMAL, no changes needed.\n";
        }
    }
    
    echo "Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
