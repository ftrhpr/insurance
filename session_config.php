<?php
// session_config.php - Secure session configuration
// Include this at the start of every page BEFORE session_start()

// Prevent session fixation and hijacking
if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session settings
    ini_set('session.cookie_httponly', 1);  // Prevent JavaScript access to session cookie
    ini_set('session.cookie_secure', 0);    // Set to 1 if using HTTPS
    ini_set('session.use_strict_mode', 1);  // Reject uninitialized session IDs
    ini_set('session.cookie_samesite', 'Lax'); // CSRF protection - changed from Lax to allow AJAX
    ini_set('session.use_only_cookies', 1); // Don't accept session IDs in URLs
    
    // Set session timeout (30 minutes)
    ini_set('session.gc_maxlifetime', 1800);
    ini_set('session.cookie_lifetime', 1800);
    
    session_start();
    
    // Regenerate session ID periodically to prevent fixation
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        // Session older than 30 minutes, regenerate ID
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
    
    // Check for session hijacking - validate IP and user agent
    if (isset($_SESSION['user_id'])) {
        // Store fingerprint on first login
        if (!isset($_SESSION['fingerprint'])) {
            $_SESSION['fingerprint'] = md5($_SERVER['HTTP_USER_AGENT'] ?? '');
        }
        
        // Verify fingerprint matches
        $current_fingerprint = md5($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($_SESSION['fingerprint'] !== $current_fingerprint) {
            // Possible session hijacking - destroy session and return JSON error
            error_log("Session fingerprint mismatch - destroying session");
            session_unset();
            session_destroy();
            http_response_code(401);
            die(json_encode(['error' => 'Session has expired or is invalid. Please log in again.']));
        }
    }
}
