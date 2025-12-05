<?php
// config.php - Centralized database configuration
// Include this file in all PHP files that need database access

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'otoexpre_userdb');
define('DB_USER', 'otoexpre_userdb');
define('DB_PASS', 'p52DSsthB}=0AeZ#');

// Create PDO connection function with timeout and retry
function getDBConnection($retries = 3) {
    $lastException = null;
    
    for ($attempt = 1; $attempt <= $retries; $attempt++) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_PERSISTENT => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_CONNECT_TIMEOUT => 5
                ]
            );
            return $pdo;
        } catch(PDOException $e) {
            $lastException = $e;
            error_log("Database connection failed (attempt $attempt/$retries): " . $e->getMessage());
            
            if ($attempt < $retries) {
                usleep(500000); // Wait 0.5 seconds before retry
                continue;
            }
        }
    }
    
    // All retries failed
    error_log("All database connection attempts failed: " . $lastException->getMessage());
    throw new Exception("Database connection failed after $retries attempts");
}
?>
