<?php
// Simple test file to check if PHP is working
echo "PHP is working!";
echo "<br>Current time: " . date('Y-m-d H:i:s');
echo "<br>PHP version: " . phpversion();

// Test database connection
try {
    require_once 'config.php';
    $pdo = getDBConnection();
    echo "<br>Database connection: SUCCESS";
} catch (Exception $e) {
    echo "<br>Database connection: FAILED - " . $e->getMessage();
}

// Test language system
try {
    require_once 'language.php';
    $test = Language::get('app.title');
    echo "<br>Language system: SUCCESS - " . $test;
} catch (Exception $e) {
    echo "<br>Language system: FAILED - " . $e->getMessage();
}
?>