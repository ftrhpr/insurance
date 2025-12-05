<?php
// quick_fix.php - Try different connection configurations

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Connection Quick Fix</h1>";
echo "<hr>";

$db_name = 'otoexpre_userdb';
$db_user = 'otoexpre_userdb';
$db_pass = 'p52DSsthB}=0AeZ#';

// Try different host configurations
$hosts_to_try = [
    'localhost',
    '127.0.0.1',
    'localhost:3306',
    '127.0.0.1:3306',
];

echo "<h2>Testing Different Host Configurations...</h2>";

foreach ($hosts_to_try as $host) {
    echo "<h3>Trying: $host</h3>";
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong style='color: #155724;'>✓ SUCCESS!</strong><br>";
        echo "Connection works with host: <strong>$host</strong><br>";
        echo "MySQL Version: " . $pdo->query('SELECT VERSION()')->fetchColumn() . "<br><br>";
        
        echo "<strong>Update your PHP files with this configuration:</strong><br>";
        echo "<code style='background: #f8f9fa; padding: 10px; display: block; margin-top: 10px;'>";
        echo "\$db_host = '$host';<br>";
        echo "\$db_name = '$db_name';<br>";
        echo "\$db_user = '$db_user';<br>";
        echo "\$db_pass = '$db_pass';<br>";
        echo "</code>";
        echo "</div>";
        
        // Test if tables exist
        echo "<h4>Checking Tables:</h4>";
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('customer_reviews', $tables)) {
            echo "✓ customer_reviews table exists<br>";
        } else {
            echo "✗ customer_reviews table NOT found - need to run reviews_schema.sql<br>";
        }
        
        if (in_array('orders', $tables)) {
            echo "✓ orders table exists<br>";
        } else {
            echo "⚠ orders table NOT found - you may need to adjust the code to match your table name<br>";
        }
        
        echo "<hr>";
        break; // Stop after first success
        
    } catch(PDOException $e) {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "✗ Failed with host '$host'<br>";
        echo "Error: " . htmlspecialchars($e->getMessage()) . "<br>";
        echo "Error Code: " . $e->getCode();
        echo "</div>";
    }
}

echo "<hr>";
echo "<h2>Additional Checks</h2>";

// Check if MySQL extension is available
echo "<h3>PHP MySQL Extensions:</h3>";
echo "PDO: " . (class_exists('PDO') ? '✓ Installed' : '✗ Not Installed') . "<br>";
echo "PDO MySQL Driver: " . (in_array('mysql', PDO::getAvailableDrivers()) ? '✓ Available' : '✗ Not Available') . "<br>";
echo "MySQLi: " . (function_exists('mysqli_connect') ? '✓ Installed' : '✗ Not Installed') . "<br>";

echo "<hr>";
echo "<h2>Common Solutions if All Failed:</h2>";
echo "<ol>";
echo "<li><strong>Check MySQL is running:</strong><br>";
echo "Windows: Check 'Services' for MySQL service<br>";
echo "Linux: <code>sudo service mysql status</code></li>";

echo "<li><strong>Verify user has permissions:</strong><br>";
echo "Login to MySQL as root and run:<br>";
echo "<code style='background: #f8f9fa; padding: 5px; display: block; margin: 5px 0;'>";
echo "GRANT ALL PRIVILEGES ON otoexpre_userdb.* TO 'otoexpre_userdb'@'localhost' IDENTIFIED BY 'p52DSsthB}=0AeZ#';<br>";
echo "FLUSH PRIVILEGES;";
echo "</code></li>";

echo "<li><strong>Check if user exists:</strong><br>";
echo "<code>SELECT User, Host FROM mysql.user WHERE User='otoexpre_userdb';</code></li>";

echo "<li><strong>Create user if doesn't exist:</strong><br>";
echo "<code style='background: #f8f9fa; padding: 5px; display: block; margin: 5px 0;'>";
echo "CREATE USER 'otoexpre_userdb'@'localhost' IDENTIFIED BY 'p52DSsthB}=0AeZ#';<br>";
echo "GRANT ALL PRIVILEGES ON otoexpre_userdb.* TO 'otoexpre_userdb'@'localhost';<br>";
echo "FLUSH PRIVILEGES;";
echo "</code></li>";

echo "<li><strong>Check if database exists:</strong><br>";
echo "<code>SHOW DATABASES LIKE 'otoexpre_userdb';</code></li>";

echo "<li><strong>Create database if doesn't exist:</strong><br>";
echo "<code>CREATE DATABASE otoexpre_userdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</code></li>";

echo "</ol>";
?>
