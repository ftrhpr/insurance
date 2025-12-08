<?php
// Test with header.php
require_once 'session_config.php';

// Set required variables for header
$current_user_name = 'Test User';
$current_user_role = 'admin';

include 'header.php';
echo "Header test - working";
?>