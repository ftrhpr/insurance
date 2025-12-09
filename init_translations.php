<?php
// init_translations.php - Run translations initializer (admin only)
session_start();
require_once 'session_config.php';
require_once 'config.php';
require_once 'language.php';

// Basic auth: require admin role
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

header('Content-Type: application/json');

$ok = initialize_default_translations();
if ($ok) {
    echo json_encode(['success' => true, 'message' => 'Default translations initialized']);
} else {
    echo json_encode(['success' => false, 'message' => 'Initialization failed (check logs)']);
}

?>
