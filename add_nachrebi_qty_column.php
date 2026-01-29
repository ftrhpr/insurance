<?php
/**
 * Migration script to add nachrebi_qty column to transfers table
 * Run this file once to add the new column
 */

require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully.\n";
    
    // Check if column already exists
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = ? 
        AND TABLE_NAME = 'transfers' 
        AND COLUMN_NAME = 'nachrebi_qty'
    ");
    $checkStmt->execute([DB_NAME]);
    $exists = $checkStmt->fetchColumn();
    
    if ($exists > 0) {
        echo "Column 'nachrebi_qty' already exists in transfers table.\n";
        exit(0);
    }
    
    // Add the column
    $sql = "ALTER TABLE transfers ADD COLUMN nachrebi_qty INT DEFAULT 0 COMMENT 'Pieces quantity (ნაჭრების რაოდენობა)'";
    $pdo->exec($sql);
    
    echo "Successfully added 'nachrebi_qty' column to transfers table.\n";
    echo "Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
