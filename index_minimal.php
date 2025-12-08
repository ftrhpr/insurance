<?php
session_start();

// Simple authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Simple language function
function __($key, $default = '') {
    $fallbacks = [
        'app.title' => 'OTOMOTORS Manager Portal',
        'app.loading' => 'Loading your workspace...',
        'navigation.dashboard' => 'Dashboard'
    ];
    return $fallbacks[$key] ?? $default ?: $key;
}

$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'] ?? 'viewer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('app.title'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50">
    <div class="min-h-screen flex">
        <div class="flex-1 p-8">
            <h1 class="text-3xl font-bold text-slate-900 mb-8">Dashboard</h1>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <p>Welcome, <?php echo htmlspecialchars($current_user_name); ?>!</p>
                <p>Role: <?php echo htmlspecialchars($current_user_role); ?></p>
                <p>System is working correctly.</p>
            </div>
        </div>
    </div>
</body>
</html>