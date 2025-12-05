<?php
// includes/auth.php - Authentication check
session_start();

// Check if users table exists
function checkSystemInitialized() {
    try {
        $db_host = 'localhost';
        $db_name = 'otoexpre_userdb';
        $db_user = 'otoexpre_userdb';
        $db_pass = 'p52DSsthB}=0AeZ#';
        
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        return ($stmt->rowCount() > 0);
    } catch (PDOException $e) {
        return false;
    }
}

// If system not initialized, redirect to setup
if (!checkSystemInitialized()) {
    header('Location: setup.php');
    exit;
}

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'] ?? 'viewer';
$current_user_id = $_SESSION['user_id'] ?? null;
?>
