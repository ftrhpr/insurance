<?php
// Check if technician users exist in database
require_once 'config.php';

try {
    $pdo = getDBConnection();

    // Check all users and their roles
    $stmt = $pdo->query("SELECT id, username, full_name, email, role, status, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h1>User Database Check</h1>";
    echo "<p>Total users: " . count($users) . "</p>";

    echo "<h2>All Users:</h2>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th></tr>";

    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email'] ?: 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($user['role']) . "</td>";
        echo "<td>" . htmlspecialchars($user['status']) . "</td>";
        echo "<td>" . $user['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Check specifically for technician users
    $technicians = array_filter($users, function($user) {
        return $user['role'] === 'technician';
    });

    echo "<h2>Technician Users:</h2>";
    if (empty($technicians)) {
        echo "<p style='color: red;'>No technician users found!</p>";
        echo "<p>You need to create a technician user first.</p>";
    } else {
        echo "<p style='color: green;'>Found " . count($technicians) . " technician user(s):</p>";
        foreach ($technicians as $tech) {
            echo "<p>- " . htmlspecialchars($tech['username']) . " (" . htmlspecialchars($tech['full_name']) . ")</p>";
        }
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
}
?>