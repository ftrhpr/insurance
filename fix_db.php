<?php
/**
 * Run this file ONCE to fix missing columns error.
 * After you see "Success", delete this file.
 */

header('Content-Type: text/plain');

// --- DATABASE CONFIGURATION (Must match api.php) ---
$db_host = 'localhost';
$db_name = 'otoexpre_userdb';     
$db_user = 'otoexpre_userdb';     
$db_pass = 'p52DSsthB}=0AeZ#';     

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to database...\n";

    // 1. Check/Add 'user_response'
    $stmt = $pdo->prepare("SHOW COLUMNS FROM transfers LIKE 'user_response'");
    $stmt->execute();
    
    if ($stmt->fetch()) {
        echo "[OK] Column 'user_response' exists.\n";
    } else {
        $sql = "ALTER TABLE transfers ADD COLUMN user_response VARCHAR(50) DEFAULT 'Pending'";
        $pdo->exec($sql);
        echo "[FIXED] Column 'user_response' added.\n";
    }

    // 2. Check/Add 'service_date'
    $stmt = $pdo->prepare("SHOW COLUMNS FROM transfers LIKE 'service_date'");
    $stmt->execute();

    if ($stmt->fetch()) {
        echo "[OK] Column 'service_date' exists.\n";
    } else {
        $sql = "ALTER TABLE transfers ADD COLUMN service_date DATETIME DEFAULT NULL";
        $pdo->exec($sql);
        echo "[FIXED] Column 'service_date' added.\n";
    }

    echo "\nDone! You can now delete this file and reload your Manager Portal.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>