<?php
require_once 'session_config.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Simple language function for login
function __($key, $default = '') {
    $fallbacks = [
        'login.title' => 'OTOMOTORS Login',
        'login.welcome' => 'Welcome Back',
        'login.username' => 'Username',
        'login.password' => 'Password',
        'login.sign_in' => 'Sign In',
        'login.error' => 'Invalid credentials'
    ];
    return $fallbacks[$key] ?? $default ?: $key;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config.php';
    
    // Rate limiting - prevent brute force attacks
    $max_attempts = 5;
    $lockout_time = 900; // 15 minutes in seconds
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    // Clean old attempts
    $_SESSION['login_attempts'] = array_filter(
        $_SESSION['login_attempts'],
        fn($time) => (time() - $time) < $lockout_time
    );
    
    // Check if locked out
    if (count($_SESSION['login_attempts']) >= $max_attempts) {
        $wait_time = ceil(($lockout_time - (time() - min($_SESSION['login_attempts']))) / 60);
        $error = "Too many failed attempts. Please try again in {$wait_time} minutes.";
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        try {
            $pdo = getDBConnection();
            
            $stmt = $pdo->prepare("SELECT id, username, password, full_name, role, status FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$username]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($userData && password_verify($password, $userData['password'])) {
                // Regenerate session ID to prevent session fixation attacks
                session_regenerate_id(true);
                
                // Clear failed login attempts on success
                $_SESSION['login_attempts'] = [];
                
                // Update last login
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$userData['id']]);
                
                // Set session
                $_SESSION['user_id'] = $userData['id'];
                $_SESSION['username'] = $userData['username'];
                $_SESSION['full_name'] = $userData['full_name'];
                $_SESSION['role'] = $userData['role'];
                
                // Redirect based on role
                if ($userData['role'] === 'technician') {
                    header('Location: https://portal.otoexpress.ge/technician_dashboard.php');
                } else {
                    header('Location: index.php');
                }
                exit();
            } else {
                // Track failed attempt
                $_SESSION['login_attempts'][] = time();
                $remaining = $max_attempts - count($_SESSION['login_attempts']);
                $error = "Invalid username or password. {$remaining} attempts remaining.";
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again.';
        }
    } else {
        $error = 'Please enter both username and password';
    }
    }
}
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo __('login.title', 'Login'); ?> | Hope UI</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include __DIR__ . '/fonts/include_fonts.php'; ?>
    <style>
        body { font-family: 'BPG Arial Caps','BPG Arial', Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; }
        .glass-card { background: rgba(255,255,255,0.04); backdrop-filter: blur(8px); border-radius: 14px; }
        /* Form focus ring for accessibility */
        .focus-ring:focus { outline: 2px solid rgba(96,165,250,0.6); outline-offset: 2px; }
    </style>
</head>
<body>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900 py-8 px-4">
        <div class="w-full max-w-md glass-card shadow-2xl">
            <div class="p-6 sm:p-8">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-lg" style="background:linear-gradient(90deg,#8b5cf6,#06b6d4); color: #fff;">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7h4l3 8h7l3-8h2"/></svg>
                    </div>
                    <h1 class="mt-4 text-2xl font-semibold text-white">OTOMOTORS</h1>
                    <p class="text-sm text-slate-300 mt-1">Manager Portal — secure access</p>
                </div>

                <?php if ($error): ?>
                <div class="mb-4 p-3 rounded-md bg-red-600/10 text-red-600 text-sm flex items-start gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mt-0.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.68-1.36 3.445 0l5.516 9.814c.75 1.334-.213 2.987-1.723 2.987H4.464c-1.51 0-2.472-1.653-1.723-2.987L8.257 3.1zM11 14a1 1 0 10-2 0 1 1 0 002 0zm-1-8a1 1 0 00-.993.883L9 7v4a1 1 0 001.993.117L11 11V7a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-4" novalidate>
                    <div>
                        <label for="username" class="block text-sm text-slate-300 mb-1">Username</label>
                        <input id="username" name="username" type="text" required autocomplete="username" class="w-full rounded-lg border border-slate-700 bg-slate-900/40 text-white px-3 py-2 focus:ring-2 focus:ring-sky-400 focus:outline-none focus:ring-offset-0 focus:ring-offset-transparent" placeholder="Enter username">
                    </div>

                    <div class="relative">
                        <label for="password" class="block text-sm text-slate-300 mb-1">Password</label>
                        <input id="password" name="password" type="password" required autocomplete="current-password" class="w-full rounded-lg border border-slate-700 bg-slate-900/40 text-white px-3 py-2 focus:ring-2 focus:ring-sky-400 focus:outline-none" placeholder="Enter password">
                        <button type="button" id="togglePwd" class="absolute right-2 top-2.5 text-slate-300 text-sm">Show</button>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <label class="inline-flex items-center gap-2 text-slate-300"><input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-600 bg-slate-700/20"> Remember me</label>
                        <a href="#" class="text-sky-400">Forgot?</a>
                    </div>

                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-cyan-500 px-4 py-2 font-semibold text-white shadow-md hover:brightness-105">Sign in</button>
                </form>

                <div class="mt-6 text-center text-xs text-slate-400">© <?php echo date('Y'); ?> OTOMOTORS</div>
            </div>
        </div>
    </div>
+    <script>
+        document.getElementById('togglePwd')?.addEventListener('click', function(){
+            const pwd = document.getElementById('password');
+            if (!pwd) return;
+            if (pwd.type === 'password') { pwd.type = 'text'; this.textContent = 'Hide'; } else { pwd.type = 'password'; this.textContent = 'Show'; }
+        });
+        document.getElementById('username')?.focus();
+    </script>
</body>
</html>
