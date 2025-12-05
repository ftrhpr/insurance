<?php
// Error handling - PRODUCTION
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Vehicles [$errno] $errstr in $errfile on line $errline");
    return true;
});

set_exception_handler(function($exception) {
    error_log('Vehicles Exception: ' . $exception->getMessage());
    http_response_code(500);
    echo '<!DOCTYPE html><html><body><h1>Error</h1><p>Service unavailable. Please try again.</p></body></html>';
    exit;
});

session_start();
require_once 'includes/auth.php';
require_once 'config.php';

requireLogin();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Database - OTOMOTORS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] } } }
        };
    </script>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen">
    <div class="min-h-screen flex flex-col">
        <?php include 'includes/header.php'; ?>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8 flex-1">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900">Vehicle Database</h1>
                    <p class="text-slate-600 mt-1">Manage registered vehicles and service history</p>
                </div>
                <a href="index-modular.php" class="px-4 py-2 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors text-sm font-medium text-slate-700">
                    <i data-lucide="layout-grid" class="w-4 h-4 inline mr-2"></i>
                    Switch to Unified View
                </a>
            </div>
            
            <div id="view-vehicles" class="space-y-6">
                <?php include 'views/vehicles.php'; ?>
            </div>
        </main>
    </div>

    <?php include 'includes/modals/vehicle-modal.php'; ?>
    <div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 pointer-events-none"></div>

    <script>
        const USER_ROLE = '<?php echo $user['role']; ?>';
        const USER_NAME = '<?php echo $user['full_name']; ?>';
        const CAN_EDIT = <?php echo canEdit() ? 'true' : 'false'; ?>;
        const IS_STANDALONE = true;
    </script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/vehicles.js"></script>
    
    <script>
        // Standalone page: load data immediately
        document.addEventListener('DOMContentLoaded', function() {
            loadData().then(() => {
                loadVehicles();
            });
            if (window.lucide) lucide.createIcons();
        });
        
        // Override switchView for standalone pages
        window.switchView = function(view) {
            window.location.href = `${view}.php`;
        };
    </script>
</body>
</html>
