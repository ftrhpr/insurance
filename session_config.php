<?php
// session_config.php - Secure session configuration
// Include this at the start of every page BEFORE session_start()

// Prevent session fixation and hijacking
if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session settings
    ini_set('session.cookie_httponly', 1);  // Prevent JavaScript access to session cookie
    ini_set('session.cookie_secure', 1);    // HTTPS only (production uses HTTPS)
    ini_set('session.use_strict_mode', 1);  // Reject uninitialized session IDs
    ini_set('session.cookie_samesite', 'Lax'); // CSRF protection
    ini_set('session.use_only_cookies', 1); // Don't accept session IDs in URLs
    
    // Set session timeout (2 hours)
    ini_set('session.gc_maxlifetime', 7200);
    ini_set('session.cookie_lifetime', 7200);
    
    session_start();
    
    // Regenerate session ID periodically to prevent fixation
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        // Session older than 30 minutes, regenerate ID
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
    
    // Check for session hijacking - validate user agent and accept-language
    if (isset($_SESSION['user_id'])) {
        // Build fingerprint from multiple browser characteristics
        $fp_parts = ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        
        // Store fingerprint on first login
        if (!isset($_SESSION['fingerprint'])) {
            $_SESSION['fingerprint'] = hash('sha256', $fp_parts);
        }
        
        // Verify fingerprint matches
        $current_fingerprint = hash('sha256', $fp_parts);
        if ($_SESSION['fingerprint'] !== $current_fingerprint) {
            // Possible session hijacking - destroy session and return JSON error
            session_unset();
            session_destroy();
            http_response_code(401);
            die(json_encode(['error' => 'Session has expired or is invalid. Please log in again.']));
        }
    }
}
