<?php
// test_db_users.php - Test database connection and users table
require_once 'config.php';

echo "<h1>Database Users Test</h1>";
echo "<hr>";

try {
    $pdo = getDBConnection();
    echo "✓ Database connection successful<br>";

    // Check if users table exists
    $result = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($result->rowCount() > 0) {
        echo "✓ Users table exists<br>";

        // Check users count
        $countStmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $count = $countStmt->fetch()['count'];
        echo "✓ Users in table: $count<br>";

        if ($count > 0) {
            // Show all users
            $userStmt = $pdo->query("SELECT id, username, full_name, email, role, status, last_login, created_at FROM users ORDER BY created_at DESC");
            $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<h2>Users:</h2>";
            echo "<pre>";
            print_r($users);
            echo "</pre>";

            // Test JSON encoding
            echo "<h2>JSON Test:</h2>";
            echo "<pre>";
            echo json_encode($users);
            echo "</pre>";
        } else {
            echo "✗ No users found in database<br>";
        }
    } else {
        echo "✗ Users table does not exist<br>";
    }

} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
}
?>