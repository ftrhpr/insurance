<?php
// Header component for OTOMOTORS portal
// Usage: include 'header.php'; before any HTML output
// Make sure $current_user_name and $current_user_role are set before including

// Ensure required variables are set
if (!isset($current_user_name)) $current_user_name = $_SESSION['full_name'] ?? 'User';
if (!isset($current_user_role)) $current_user_role = $_SESSION['role'] ?? 'user';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get current page name for active navigation
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Navigation items
$nav_items = [
    'index' => ['icon' => 'layout-dashboard', 'label' => 'Dashboard', 'url' => 'index.php'],
    'vehicles' => ['icon' => 'database', 'label' => 'Vehicle DB', 'url' => 'vehicles.php'],
    'reviews' => ['icon' => 'star', 'label' => 'Reviews', 'url' => 'reviews.php'],
    'templates' => ['icon' => 'message-square-dashed', 'label' => 'SMS Templates', 'url' => 'templates.php']
];

// Add Users page only for admins
if ($current_user_role === 'admin') {
    $nav_items['users'] = ['icon' => 'users', 'label' => 'Users', 'url' => 'users.php'];
    $nav_items['translations'] = ['icon' => 'languages', 'label' => 'Translations', 'url' => 'translations.php'];
}
?>
<!-- Simple header for debugging -->
<nav style="background: lightblue; padding: 10px; border-bottom: 1px solid #ccc;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1 style="margin: 0; color: #333;">OTOMOTORS - <?php echo ucfirst($current_page); ?></h1>
        <div style="color: #666;">
            Welcome, <?php echo htmlspecialchars($current_user_name); ?> (<?php echo htmlspecialchars($current_user_role); ?>)
            <a href="logout.php" style="margin-left: 20px; color: #d00;">Logout</a>
        </div>
    </div>
</nav>
