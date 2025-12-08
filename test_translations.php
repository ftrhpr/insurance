<?php
// Test script to initialize translations
require_once 'config.php';
require_once 'language.php';

// Initialize default translations
$result = initialize_default_translations();

if ($result) {
    echo "Default translations initialized successfully!\n";

    // Test translation retrieval
    echo "\nTesting translation retrieval:\n";
    echo "dashboard.title: " . __('dashboard.title') . "\n";
    echo "dashboard.quick_import: " . __('dashboard.quick_import') . "\n";
    echo "dashboard.manual_create: " . __('dashboard.manual_create') . "\n";

    // Test language switching
    echo "\nTesting language switching to Georgian:\n";
    set_language('ka');
    echo "dashboard.title (Georgian): " . __('dashboard.title') . "\n";

} else {
    echo "Failed to initialize translations.\n";
}
?>