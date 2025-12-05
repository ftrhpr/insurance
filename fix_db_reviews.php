<?php
/**
 * REVIEWS FEATURE FIXER
 * Run this file once to add the missing review columns to your database.
 */

header('Content-Type: text/plain');

// --- DATABASE CONFIGURATION ---
$db_host = 'localhost';
$db_name = 'otoexpre_userdb';     
$db_user = 'otoexpre_userdb';     
$db_pass = 'p52DSsthB}=0AeZ#';     

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to database...\n";

    // 1. Check/Add 'review_stars'
    $stmt = $pdo->prepare("SHOW COLUMNS FROM transfers LIKE 'review_stars'");
    $stmt->execute();
    
    if ($stmt->fetch()) {
        echo "[OK] Column 'review_stars' exists.\n";
    } else {
        $sql = "ALTER TABLE transfers ADD COLUMN review_stars INT DEFAULT NULL";
        $pdo->exec($sql);
        echo "[FIXED] Column 'review_stars' added.\n";
    }

    // 2. Check/Add 'review_comment'
    $stmt = $pdo->prepare("SHOW COLUMNS FROM transfers LIKE 'review_comment'");
    $stmt->execute();

    if ($stmt->fetch()) {
        echo "[OK] Column 'review_comment' exists.\n";
    } else {
        $sql = "ALTER TABLE transfers ADD COLUMN review_comment TEXT DEFAULT NULL";
        $pdo->exec($sql);
        echo "[FIXED] Column 'review_comment' added.\n";
    }

    echo "\nDatabase update complete. You can delete this file and reload your manager portal.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>