<?php
// Authentication and session management

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function requireRole($required_role) {
    $user_role = $_SESSION['role'] ?? 'viewer';
    $hierarchy = ['viewer' => 1, 'manager' => 2, 'admin' => 3];
    
    if ($hierarchy[$user_role] < $hierarchy[$required_role]) {
        http_response_code(403);
        die('Access denied');
    }
}

function isAdmin() {
    return ($_SESSION['role'] ?? '') === 'admin';
}

function isManager() {
    return in_array($_SESSION['role'] ?? '', ['admin', 'manager']);
}

function canEdit() {
    return isManager();
}

function getCurrentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? 'User',
        'role' => $_SESSION['role'] ?? 'viewer'
    ];
}
?>
