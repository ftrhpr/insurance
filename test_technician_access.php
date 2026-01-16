<?php
// Test technician user access
require_once 'session_config.php';
require_once 'config.php';

echo "<h1>Technician Access Test</h1>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p>Not logged in. <a href='login.php'>Login</a></p>";
    exit;
}

echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";
echo "<p>Username: " . ($_SESSION['username'] ?? 'N/A') . "</p>";
echo "<p>Role: " . ($_SESSION['role'] ?? 'N/A') . "</p>";
echo "<p>Full Name: " . ($_SESSION['full_name'] ?? 'N/A') . "</p>";

// Check if user is technician
if (($_SESSION['role'] ?? '') !== 'technician') {
    echo "<p style='color: red;'>ERROR: User is not a technician! Current role: " . ($_SESSION['role'] ?? 'none') . "</p>";
    echo "<p><a href='logout.php'>Logout</a> and try logging in with a technician account.</p>";
    exit;
}

echo "<p style='color: green;'>✓ User is properly logged in as technician</p>";

// Test database connection
try {
    $pdo = getDBConnection();
    echo "<p style='color: green;'>✓ Database connection successful</p>";

    // Check if technician user exists in database
    $stmt = $pdo->prepare("SELECT id, username, role, status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "<p style='color: green;'>✓ User found in database:</p>";
        echo "<ul>";
        echo "<li>ID: " . $user['id'] . "</li>";
        echo "<li>Username: " . $user['username'] . "</li>";
        echo "<li>Role: " . $user['role'] . "</li>";
        echo "<li>Status: " . $user['status'] . "</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>✗ User not found in database!</p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='technician_dashboard.php'>Go to Technician Dashboard</a></p>";
echo "<p><a href='logout.php'>Logout</a></p>";
?>