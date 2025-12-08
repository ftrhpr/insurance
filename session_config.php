// DEBUG: Confirm session_config.php is loaded
echo "SESSION_CONFIG_LOADED";
file_put_contents(__DIR__ . '/error_log', date('Y-m-d H:i:s') . " SESSION_CONFIG_LOADED\n", FILE_APPEND);
<?php
// Debug: log and display all errors in session_config.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
file_put_contents(__DIR__ . '/error_log', date('Y-m-d H:i:s') . " SESSION_CONFIG: " . json_encode($_SERVER) . "\n", FILE_APPEND);
// session_config.php - Secure session configuration
// Include this at the start of every page BEFORE session_start()

// Prevent session fixation and hijacking
if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session settings
    ini_set('session.cookie_httponly', 1);  // Prevent JavaScript access to session cookie
    ini_set('session.cookie_secure', 0);    // Set to 1 if using HTTPS in production
    ini_set('session.use_strict_mode', 1);  // Reject uninitialized session IDs
    ini_set('session.cookie_samesite', 'Lax'); // CSRF protection
    ini_set('session.use_only_cookies', 1); // Don't accept session IDs in URLs
    ini_set('session.sid_length', 48);      // Longer session IDs (more secure)
    ini_set('session.sid_bits_per_character', 6); // More entropy
    
    // Set session timeout (30 minutes)
    ini_set('session.gc_maxlifetime', 1800);
    ini_set('session.cookie_lifetime', 0); // Session cookie (expires on browser close)
    
    session_start();
    
    // Regenerate session ID periodically to prevent fixation
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        // Session older than 30 minutes, regenerate ID
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
    
    // Check for session hijacking - validate user agent
    if (isset($_SESSION['user_id'])) {
        // Activity timeout check (30 minutes of inactivity)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            session_unset();
            session_destroy();
            session_start();
            session_regenerate_id(true);
            // If API request, return JSON error instead of redirect
            if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'api.php') !== false) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['error' => 'Session timeout']);
                exit();
            } else {
                header('Location: login.php?error=timeout');
                exit();
            }
        }
        }
        $_SESSION['last_activity'] = time();
        
        // Store fingerprint on first login
        if (!isset($_SESSION['fingerprint'])) {
            $_SESSION['fingerprint'] = md5($_SERVER['HTTP_USER_AGENT'] ?? '');
        }
        
        // Verify fingerprint matches (detect session hijacking)
        $current_fingerprint = md5($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($_SESSION['fingerprint'] !== $current_fingerprint) {
            // Possible session hijacking - destroy session
            session_unset();
            session_destroy();
            session_start();
            session_regenerate_id(true);
            header('Location: login.php?error=session_invalid');
            exit();
        }
        
        // Optional: IP validation (can be too strict with mobile networks)
        // Uncomment if your users have stable IPs
        /*
        if (!isset($_SESSION['ip_address'])) {
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
        if ($_SESSION['ip_address'] !== ($_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
            session_unset();
            session_destroy();
            session_start();
            session_regenerate_id(true);
            header('Location: login.php?error=session_invalid');
            exit();
        }
        */
    }
}
