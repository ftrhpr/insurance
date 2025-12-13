<?php
// test_connection.php - Test database connection and diagnose issues

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Test</h2>";
echo "<hr>";

// Test credentials
$db_host = 'localhost';
$db_name = 'otoexpre_userdb';
$db_user = 'otoexpre_userdb';
$db_pass = 'p52DSsthB}=0AeZ#';

echo "<h3>1. Testing MySQL Extension</h3>";
if (function_exists('mysqli_connect')) {
    echo "✓ MySQLi extension is installed<br>";
} else {
    echo "✗ MySQLi extension is NOT installed<br>";
}

if (class_exists('PDO')) {
    echo "✓ PDO extension is installed<br>";
} else {
    echo "✗ PDO extension is NOT installed<br>";
}

if (in_array('mysql', PDO::getAvailableDrivers())) {
    echo "✓ PDO MySQL driver is available<br>";
} else {
    echo "✗ PDO MySQL driver is NOT available<br>";
}

echo "<hr>";
echo "<h3>2. Testing Connection with MySQLi</h3>";
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    echo "✗ MySQLi Connection failed: " . $mysqli->connect_error . "<br>";
    echo "Error code: " . $mysqli->connect_errno . "<br>";
} else {
    echo "✓ MySQLi connection successful!<br>";
    echo "MySQL Version: " . $mysqli->server_info . "<br>";
    $mysqli->close();
}

echo "<hr>";
echo "<h3>3. Testing Connection with PDO</h3>";
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ PDO connection successful!<br>";
    
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "MySQL Version: $version<br>";
    
    // Test if reviews table exists
    echo "<hr>";
    echo "<h3>4. Testing Database Tables</h3>";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in database:<br>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>" . htmlspecialchars($table);
        if ($table === 'customer_reviews') {
            echo " <strong style='color: green;'>✓ (reviews table found)</strong>";
        } elseif ($table === 'orders') {
            echo " <strong style='color: green;'>✓ (orders table found)</strong>";
        }
        echo "</li>";
    }
    echo "</ul>";
    
    // Check if customer_reviews table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'customer_reviews'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ customer_reviews table exists</p>";
        
        // Check table structure
        $columns = $pdo->query("SHOW COLUMNS FROM customer_reviews")->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>Table structure:</p>";
        echo "<ul>";
        foreach ($columns as $col) {
            echo "<li>" . htmlspecialchars($col['Field']) . " (" . htmlspecialchars($col['Type']) . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>✗ customer_reviews table does NOT exist</p>";
        echo "<p><strong>Action Required:</strong> Run the SQL schema from reviews_schema.sql</p>";
    }
    
    // Check orders table
    $stmt = $pdo->query("SHOW TABLES LIKE 'orders'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ orders table exists</p>";
        $columns = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>Orders table columns:</p>";
        echo "<ul>";
        foreach ($columns as $col) {
            echo "<li>" . htmlspecialchars($col['Field']) . " (" . htmlspecialchars($col['Type']) . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: orange;'>⚠ orders table does NOT exist (you'll need to create it or update the code to match your table name)</p>";
    }
    
} catch(PDOException $e) {
    echo "✗ PDO Connection failed: " . $e->getMessage() . "<br>";
    echo "Error code: " . $e->getCode() . "<br><br>";
    
    echo "<h3>Possible Solutions:</h3>";
    echo "<ol>";
    echo "<li><strong>Check credentials:</strong> Verify username, password, and database name are correct</li>";
    echo "<li><strong>Check user permissions:</strong> Run this SQL as root:<br>
        <code>GRANT ALL PRIVILEGES ON otoexpre_userdb.* TO 'otoexpre_userdb'@'localhost' IDENTIFIED BY 'p52DSsthB}=0AeZ#';<br>
        FLUSH PRIVILEGES;</code></li>";
    echo "<li><strong>Check if user exists:</strong> Run as root:<br>
        <code>SELECT User, Host FROM mysql.user WHERE User='otoexpre_userdb';</code></li>";
    echo "<li><strong>Try different host:</strong> Instead of 'localhost', try '127.0.0.1'</li>";
    echo "<li><strong>Check MySQL is running:</strong> Ensure MySQL service is active</li>";
    echo "</ol>";
}

echo "<hr>";
echo "<h3>Server Information</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
?>
