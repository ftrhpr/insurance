<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    $sql = "ALTER TABLE transfers ADD COLUMN due_date DATETIME NULL";
    $pdo->exec($sql);
    echo "Column 'due_date' added successfully to transfers table.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>