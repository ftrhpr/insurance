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

<?php
require_once 'session_config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

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
    $max_attempts = 5;
    $lockout_time = 900;

    if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = [];
    $_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'], fn($t) => (time() - $t) < $lockout_time);

    if (count($_SESSION['login_attempts']) >= $max_attempts) {
        $error = 'Too many failed attempts. Please try again later.';
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
                    session_regenerate_id(true);
                    $_SESSION['login_attempts'] = [];
                    $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $update->execute([$userData['id']]);
                    $_SESSION['user_id'] = $userData['id'];
                    $_SESSION['username'] = $userData['username'];
                    $_SESSION['full_name'] = $userData['full_name'];
                    $_SESSION['role'] = $userData['role'];
                    header('Location: index.php'); exit();
                } else {
                    $_SESSION['login_attempts'][] = time();
                    $remaining = $max_attempts - count($_SESSION['login_attempts']);
                    $error = "Invalid username or password. {$remaining} attempts remaining.";
                }
            } catch (Exception $e) {
                $error = 'Database error. Please try again.';
            }
        } else {
            $error = 'Please enter both username and password';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo __('login.title', 'Login'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <?php if (file_exists(__DIR__ . '/fonts/include_fonts.php')) include __DIR__ . '/fonts/include_fonts.php'; ?>
    <style>body{font-family:'BPG Arial Caps','BPG Arial',Arial,sans-serif}input::placeholder{color:rgba(107,114,128,0.6);opacity:1}</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-sky-600 to-violet-700 flex items-center justify-center p-6">
    <div class="w-full max-w-md glass-card rounded-2xl shadow-2xl overflow-hidden">
        <div class="p-8 text-center bg-gradient-to-r from-indigo-600 to-purple-600 text-white">
            <div class="flex items-center justify-center mb-4"><i data-lucide="shield-check" class="w-12 h-12"></i></div>
            <h1 class="text-2xl font-extrabold">OTOMOTORS Manager Portal</h1>
            <p class="mt-1 text-sm opacity-90">Secure manager access</p>
        </div>
        <div class="p-6">
            <?php if ($error): ?><div class="mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <form method="POST" action="" class="space-y-4">
                <div>
                    <label for="username" class="block text-sm font-semibold text-slate-700 mb-1">Username</label>
                    <input id="username" name="username" type="text" required autofocus autocomplete="username" placeholder="manager" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-700 mb-1">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
                <div class="flex items-center justify-between">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" class="rounded text-indigo-600"> <span>Remember me</span></label>
                    <a href="#" class="text-sm text-indigo-600 hover:underline">Forgot?</a>
                </div>
                <button type="submit" class="w-full btn-gradient inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-white font-semibold"><i data-lucide="log-in" class="w-4 h-4"></i> Sign in</button>
            </form>
        </div>
        <div class="px-6 py-4 text-center text-xs text-slate-500">© <?php echo date('Y'); ?> OTOMOTORS</div>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
