<?php
// Error handling configuration
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log');

// Custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $error_types = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_NOTICE => 'NOTICE',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE'
    ];
    $type = $error_types[$errno] ?? 'UNKNOWN';
    error_log("[$type] $errstr in $errfile on line $errline");
    return true;
});

// Custom exception handler
set_exception_handler(function($exception) {
    error_log('Uncaught Exception: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ' on line ' . $exception->getLine());
    error_log('Stack trace: ' . $exception->getTraceAsString());
    
    // Show generic error page to user
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>Error</title></head><body><h1>Service Temporarily Unavailable</h1><p>An error occurred. Please try again later.</p></body></html>';
    exit;
});

session_start();
require_once 'includes/auth.php';
require_once 'config.php';

// Require authentication
requireLogin();

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTOMOTORS Manager Portal</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Firebase -->
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd',
                            400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8',
                            800: '#1e40af', 900: '#1e3a8a', 950: '#172554'
                        }
                    },
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
                }
            }
        };
    </script>

    <style>
        .nav-active { @apply bg-slate-900 text-white shadow-sm; }
        .nav-inactive { @apply text-slate-500 hover:text-slate-900 hover:bg-white; }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen">
    <div class="min-h-screen flex flex-col">
        <?php include 'includes/header.php'; ?>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8 flex-1">
            <?php include 'views/dashboard.php'; ?>
            <?php include 'views/vehicles.php'; ?>
            <?php include 'views/reviews.php'; ?>
            <?php include 'views/templates.php'; ?>
            <?php if (isAdmin()): ?>
                <?php include 'views/users.php'; ?>
            <?php endif; ?>
        </main>
    </div>

    <!-- Modals -->
    <?php include 'includes/modals/edit-modal.php'; ?>
    <?php include 'includes/modals/vehicle-modal.php'; ?>
    <?php include 'includes/modals/user-modals.php'; ?>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 pointer-events-none"></div>

    <!-- Scripts -->
    <script>
        // Inject PHP variables into JavaScript
        const USER_ROLE = '<?php echo $user['role']; ?>';
        const USER_NAME = '<?php echo $user['full_name']; ?>';
        const CAN_EDIT = <?php echo canEdit() ? 'true' : 'false'; ?>;
    </script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/firebase-config.js"></script>
    <script src="assets/js/transfers.js"></script>
    <script src="assets/js/vehicles.js"></script>
    <script src="assets/js/reviews.js"></script>
    <script src="assets/js/sms-templates.js"></script>
    <?php if (isAdmin()): ?>
    <script src="assets/js/user-management.js"></script>
    <?php endif; ?>
</body>
</html>
