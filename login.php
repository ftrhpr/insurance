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
                
                header('Location: index.php');
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
    
    <!-- Bootstrap 5.3 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/fonts/include_fonts.php'; ?>
    
    <style>
        :root {
            --bs-primary: #573BFF;
            --bs-success: #17904b;
            --bs-danger: #FF6171;
        }
        
        body {
            font-family: 'BPG Arial Caps', 'BPG Arial', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            max-width: 450px;
            width: 100%;
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--bs-primary) 0%, #8662FF 100%);
            padding: 3rem 2rem;
            text-align: center;
            color: #fff;
        }
        
        .login-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            backdrop-filter: blur(10px);
        }
        
        .login-icon i {
            font-size: 2.5rem;
        }
        
        .login-title {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 0.5rem;
        }
        
        .login-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .login-body {
            padding: 2.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #1E2139;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border-radius: 12px;
            border: 2px solid #e0e0e0;
            padding: 0.9rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.2rem rgba(87, 59, 255, 0.15);
        }
        
        .input-group-text {
            background: transparent;
            border: 2px solid #e0e0e0;
            border-right: none;
            border-radius: 12px 0 0 12px;
            color: #7C8DB0;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }
        
        .input-group .form-control:focus {
            border-left: none;
        }
        
        .input-group:focus-within .input-group-text {
            border-color: var(--bs-primary);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--bs-primary) 0%, #8662FF 100%);
            border: none;
            border-radius: 12px;
            padding: 0.9rem 2rem;
            font-weight: 700;
            font-size: 1rem;
            color: #fff;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 1.5rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(87, 59, 255, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .alert-danger {
            background: rgba(255, 97, 113, 0.1);
            color: var(--bs-danger);
        }
        
        .login-footer {
            text-align: center;
            padding: 1.5rem 2.5rem 2.5rem;
            color: #7C8DB0;
            font-size: 0.85rem;
        }
        /* Ensure placeholders are visible and have sufficient contrast */
        input::placeholder, textarea::placeholder { color: rgba(255,255,255,0.65); opacity: 1; }
        .gradient-left { background: linear-gradient(135deg, #573BFF 0%, #8662FF 100%); }
        @media (max-width: 576px) {
            .login-card { border-radius: 12px; }
            .login-icon { width: 64px; height: 64px; }
        }
    </style>
</head>
<body>
    <div class="min-vh-100 d-flex align-items-center justify-content-center p-4" style="background: linear-gradient(180deg,#0f172a 0%, #0b1220 60%);">
        <div class="login-card w-100" style="max-width:420px; background: rgba(255,255,255,0.04); border-radius:16px; box-shadow: 0 8px 40px rgba(2,6,23,0.6); overflow:hidden; backdrop-filter: blur(8px);">
            <div class="p-4">
                <div class="text-center mb-3">
                    <div class="login-icon mx-auto mb-2" style="width:72px;height:72px;border-radius:12px;background:linear-gradient(135deg,#6d28d9,#06b6d4);display:flex;align-items:center;justify-content:center;color:#fff;font-size:28px;">
                        <i class="fas fa-car-side"></i>
                    </div>
                    <h3 class="mb-0 text-white fw-bold">OTOMOTORS</h3>
                    <p class="text-muted small mb-0">Manager Portal</p>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger mb-3" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="" class="mt-2">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control bg-transparent text-white border-0" id="username" name="username" placeholder="Username" required autofocus autocomplete="username" style="border:1px solid rgba(255,255,255,0.06); border-radius:10px; padding:1rem;">
                        <label for="username" class="text-white">Username</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="password" class="form-control bg-transparent text-white border-0" id="password" name="password" placeholder="Password" required autocomplete="current-password" style="border:1px solid rgba(255,255,255,0.06); border-radius:10px; padding:1rem;">
                        <label for="password" class="text-white">Password</label>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="form-check text-white">
                            <input class="form-check-input" type="checkbox" value="1" id="rememberMe" name="remember" style="transform:scale(1.05);">
                            <label class="form-check-label small" for="rememberMe">Remember me</label>
                        </div>
                        <a href="#" class="small text-white">Forgot?</a>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" style="background:linear-gradient(90deg,#6d28d9,#06b6d4); border:none; padding:0.85rem 1rem; border-radius:10px; font-weight:700;">
                        <i class="fas fa-sign-in-alt me-2"></i> <?php echo __('login.sign_in', 'Sign In'); ?>
                    </button>
                </form>

                <div class="text-center mt-4 small text-muted" style="color:rgba(255,255,255,0.6);">
                    © <?php echo date('Y'); ?> OTOMOTORS
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
