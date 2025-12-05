<?php
// Error handling configuration
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log');

// Custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Login [$errno] $errstr in $errfile on line $errline");
    return true;
});

session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config.php';
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        try {
            $pdo = getDBConnection();
            
            $stmt = $pdo->prepare("SELECT id, username, password, full_name, role, status FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Update last login
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                
                header('Location: index.php');
                exit;
            } else {
                $error = 'Invalid username or password';
            }
        } catch (PDOException $e) {
            error_log('Login DB Error: ' . $e->getMessage());
            $error = 'Unable to connect to the database. Please try again.';
        } catch (Exception $e) {
            error_log('Login Error: ' . $e->getMessage());
            if (strpos($e->getMessage(), 'connection failed') !== false) {
                $error = 'Server connection failed. Please check your network and try again.';
            } else {
                $error = 'An error occurred. Please try again later.';
            }
        }
    } else {
        $error = 'Please enter both username and password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - OTOMOTORS Manager Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-8 text-white">
                <div class="flex items-center justify-center mb-4">
                    <i data-lucide="shield-check" class="w-16 h-16"></i>
                </div>
                <h1 class="text-3xl font-bold text-center">OTOMOTORS</h1>
                <p class="text-center text-blue-100 mt-2">Manager Portal</p>
            </div>
            
            <div class="p-8">
                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                        <i data-lucide="alert-circle" class="w-5 h-5 mr-2"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="space-y-6">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="user" class="w-5 h-5 text-gray-400"></i>
                            </div>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                required
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                placeholder="Enter your username"
                                autofocus
                            >
                        </div>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="lock" class="w-5 h-5 text-gray-400"></i>
                            </div>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                required
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                placeholder="Enter your password"
                            >
                        </div>
                    </div>
                    
                    <button 
                        type="submit"
                        class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-3 rounded-lg font-semibold hover:from-blue-700 hover:to-indigo-700 transition-all duration-200 flex items-center justify-center space-x-2 shadow-lg hover:shadow-xl"
                    >
                        <span>Sign In</span>
                        <i data-lucide="log-in" class="w-5 h-5"></i>
                    </button>
                </form>
                
                <div class="mt-6 text-center text-sm text-gray-500">
                    <p>Default credentials: <strong>admin</strong> / <strong>admin123</strong></p>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-6 text-gray-600 text-sm">
            <p>© 2025 OTOMOTORS. All rights reserved.</p>
        </div>
    </div>
    
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
