<?php
// Simple test to isolate the issue
session_start();
require_once 'session_config.php';
require_once 'config.php';

echo "Session config loaded successfully<br>";
echo "Config loaded successfully<br>";

// Test database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connection successful<br>";
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "<br>";
}

echo "Basic test completed";
?>