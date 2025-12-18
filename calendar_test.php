<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'session_config.php';
require_once 'config.php';
require_once 'language.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get current user info
$current_user_name = $_SESSION['full_name'] ?? 'Manager';
$current_user_role = $_SESSION['role'] ?? 'manager';

// Simple test - just output basic HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar Test - OTOMOTORS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
    <div class="p-8">
        <h1 class="text-2xl font-bold mb-4">Calendar Test Page</h1>
        <p>If you can see this, the basic includes are working.</p>
        <p>User: <?php echo htmlspecialchars($current_user_name); ?></p>
        <p>Role: <?php echo htmlspecialchars($current_user_role); ?></p>
        <a href="index.php" class="mt-4 inline-block px-4 py-2 bg-blue-500 text-white rounded">Back to Dashboard</a>
    </div>
</body>
</html>