<?php
// debug_users.php - Debug script for users page
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Users Debug</h1>";
echo "<hr>";

// Check session
echo "<h2>Session Check</h2>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'not set') . "<br>";
echo "Username: " . ($_SESSION['username'] ?? 'not set') . "<br>";
echo "Role: " . ($_SESSION['role'] ?? 'not set') . "<br>";
echo "Full Name: " . ($_SESSION['full_name'] ?? 'not set') . "<br>";
echo "<hr>";

// Check database
echo "<h2>Database Check</h2>";
require_once 'config.php';

try {
    $pdo = getDBConnection();
    echo "✓ Database connection successful<br>";

    // Check users table
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Users table exists<br>";

        // Check users count
        $countStmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $count = $countStmt->fetch()['count'];
        echo "✓ Users in table: $count<br>";

        if ($count > 0) {
            // Show first user
            $userStmt = $pdo->query("SELECT id, username, full_name, role, status FROM users LIMIT 1");
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            echo "✓ Sample user: " . htmlspecialchars($user['username']) . " (" . $user['role'] . ")<br>";
        }
    } else {
        echo "✗ Users table does not exist<br>";
    }

} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
}

// Simulate API call (bypass session check for debugging)
try {
    $stmt = $pdo->prepare("SELECT id, username, full_name, email, role, status, last_login, created_at FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✓ API query successful<br>";
    echo "✓ Users returned: " . count($users) . "<br>";
    if (count($users) > 0) {
        echo "✓ First user: " . htmlspecialchars($users[0]['username']) . "<br>";
        echo "<pre>";
        print_r($users[0]);
        echo "</pre>";
    } else {
        echo "✗ No users found in database<br>";
    }
} catch (Exception $e) {
    echo "✗ API query error: " . $e->getMessage() . "<br>";
}
?>