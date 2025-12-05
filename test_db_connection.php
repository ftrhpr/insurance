<?php
// test_db_connection.php - Database connection diagnostic tool

header('Content-Type: text/html; charset=utf-8');

$db_host = 'localhost';
$db_name = 'otoexpre_userdb';
$db_user = 'otoexpre_userdb';
$db_pass = 'p52DSsthB}=0AeZ#';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Test</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success {
            color: #059669;
            border-left: 4px solid #059669;
        }
        .error {
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }
        .info {
            color: #2563eb;
            border-left: 4px solid #2563eb;
        }
        h1 {
            color: #1f2937;
        }
        pre {
            background: #f3f4f6;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .status {
            font-weight: bold;
            font-size: 18px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        table td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        table td:first-child {
            font-weight: bold;
            width: 200px;
        }
    </style>
</head>
<body>
    <h1>🔍 Database Connection Diagnostic</h1>
    
    <?php
    echo '<div class="test-box info">';
    echo '<h3>Configuration</h3>';
    echo '<table>';
    echo '<tr><td>Host:</td><td>' . htmlspecialchars($db_host) . '</td></tr>';
    echo '<tr><td>Database:</td><td>' . htmlspecialchars($db_name) . '</td></tr>';
    echo '<tr><td>Username:</td><td>' . htmlspecialchars($db_user) . '</td></tr>';
    echo '<tr><td>Password:</td><td>' . str_repeat('*', strlen($db_pass)) . '</td></tr>';
    echo '</table>';
    echo '</div>';
    
    // Test 1: PDO Extension
    echo '<div class="test-box ' . (extension_loaded('pdo') ? 'success' : 'error') . '">';
    echo '<h3>Test 1: PDO Extension</h3>';
    if (extension_loaded('pdo')) {
        echo '<p class="status">✓ PDO extension is loaded</p>';
    } else {
        echo '<p class="status">✗ PDO extension is NOT loaded</p>';
        echo '<p>Please enable PDO in php.ini</p>';
    }
    echo '</div>';
    
    // Test 2: PDO MySQL Driver
    echo '<div class="test-box ' . (extension_loaded('pdo_mysql') ? 'success' : 'error') . '">';
    echo '<h3>Test 2: PDO MySQL Driver</h3>';
    if (extension_loaded('pdo_mysql')) {
        echo '<p class="status">✓ PDO MySQL driver is loaded</p>';
    } else {
        echo '<p class="status">✗ PDO MySQL driver is NOT loaded</p>';
        echo '<p>Please enable pdo_mysql in php.ini</p>';
    }
    echo '</div>';
    
    // Test 3: Database Connection
    echo '<div class="test-box">';
    echo '<h3>Test 3: Database Connection</h3>';
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo '<p class="status success">✓ Successfully connected to database!</p>';
        
        // Get MySQL version
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        echo '<p><strong>MySQL Version:</strong> ' . htmlspecialchars($version) . '</p>';
        
    } catch (PDOException $e) {
        echo '<p class="status error">✗ Connection failed</p>';
        echo '<p><strong>Error:</strong></p>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        
        if (strpos($e->getMessage(), 'Access denied') !== false) {
            echo '<p><strong>Suggestion:</strong> Check database username and password</p>';
        } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
            echo '<p><strong>Suggestion:</strong> Database "' . $db_name . '" does not exist. Create it first.</p>';
        } elseif (strpos($e->getMessage(), 'Connection refused') !== false) {
            echo '<p><strong>Suggestion:</strong> MySQL server is not running or not accessible</p>';
        }
        echo '</div>';
        echo '</body></html>';
        exit;
    }
    echo '</div>';
    
    // Test 4: Tables Check
    echo '<div class="test-box">';
    echo '<h3>Test 4: Tables Check</h3>';
    try {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($tables) > 0) {
            echo '<p class="status success">✓ Found ' . count($tables) . ' tables</p>';
            echo '<ul>';
            foreach ($tables as $table) {
                echo '<li>' . htmlspecialchars($table);
                
                // Count rows for each table
                try {
                    $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                    echo ' <span style="color: #6b7280;">(' . $count . ' rows)</span>';
                } catch (PDOException $e) {
                    echo ' <span style="color: #dc2626;">(error counting)</span>';
                }
                
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p class="status error">✗ No tables found</p>';
            echo '<p>Database is empty. Run <strong>fix_db_all.php</strong> to create tables.</p>';
        }
    } catch (PDOException $e) {
        echo '<p class="status error">✗ Could not list tables</p>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    }
    echo '</div>';
    
    // Test 5: Users Table
    echo '<div class="test-box">';
    echo '<h3>Test 5: Users Table</h3>';
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() > 0) {
            echo '<p class="status success">✓ Users table exists</p>';
            
            // Count users
            $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            echo '<p>Total users: <strong>' . $userCount . '</strong></p>';
            
            if ($userCount > 0) {
                echo '<p>User accounts:</p>';
                echo '<ul>';
                $users = $pdo->query("SELECT username, full_name, role, status FROM users")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($users as $user) {
                    echo '<li><strong>' . htmlspecialchars($user['username']) . '</strong> - ' . 
                         htmlspecialchars($user['full_name']) . ' (' . 
                         htmlspecialchars($user['role']) . ', ' . 
                         htmlspecialchars($user['status']) . ')</li>';
                }
                echo '</ul>';
            } else {
                echo '<p class="status error">✗ No users found. Run <strong>fix_db_all.php</strong> to create default admin.</p>';
            }
        } else {
            echo '<p class="status error">✗ Users table does not exist</p>';
            echo '<p>Run <strong>fix_db_all.php</strong> to create the users table and default admin account.</p>';
        }
    } catch (PDOException $e) {
        echo '<p class="status error">✗ Error checking users table</p>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    }
    echo '</div>';
    
    // Summary
    echo '<div class="test-box info">';
    echo '<h3>Next Steps</h3>';
    echo '<ol>';
    echo '<li>If all tests pass, you can <a href="login.php">proceed to login</a></li>';
    echo '<li>If users table is missing, <a href="fix_db_all.php">run database migration</a></li>';
    echo '<li>Default credentials: <strong>admin</strong> / <strong>admin123</strong></li>';
    echo '</ol>';
    echo '</div>';
    ?>
    
</body>
</html>
