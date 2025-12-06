<?php
// Debug script to test public view API endpoint
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Public View Debug Tool</h2>";
echo "<hr>";

// Database credentials
$db_host = 'localhost';
$db_name = 'otoexpre_userdb';
$db_user = 'otoexpre_userdb';
$db_pass = 'p52DSsthB}=0AeZ#';

// Test ID (change this to an actual ID from your database)
$test_id = intval($_GET['test_id'] ?? 1);

echo "<h3>Testing with ID: " . htmlspecialchars($test_id, ENT_QUOTES, 'UTF-8') . "</h3>";
echo "<p><a href='?test_id=" . ($test_id + 1) . "'>Try ID " . ($test_id + 1) . "</a></p>";
echo "<hr>";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Database connection successful<br><br>";
    
    // Test 1: Check if transfers table exists
    echo "<h3>1. Checking transfers table</h3>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'transfers'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Table 'transfers' exists<br>";
    } else {
        echo "✗ Table 'transfers' does NOT exist<br>";
        exit;
    }
    
    // Test 2: Check columns
    echo "<h3>2. Checking columns</h3>";
    $stmt = $pdo->query("DESCRIBE transfers");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Available columns: " . implode(', ', $columns) . "<br>";
    
    $required = ['id', 'name', 'plate', 'status', 'service_date', 'user_response', 'review_stars', 'review_comment'];
    foreach ($required as $col) {
        if (in_array($col, $columns)) {
            echo "✓ Column '$col' exists<br>";
        } else {
            echo "✗ Column '$col' is MISSING<br>";
        }
    }
    
    // Test 3: Count total transfers
    echo "<h3>3. Checking data</h3>";
    $stmt = $pdo->query("SELECT COUNT(*) FROM transfers");
    $count = $stmt->fetchColumn();
    echo "Total transfers in database: <strong>$count</strong><br>";
    
    if ($count == 0) {
        echo "<p style='color: red;'>⚠️ No transfers found in database! This is why you're getting 'not found' error.</p>";
        echo "<p>Add some test data first.</p>";
        exit;
    }
    
    // Test 4: List all IDs
    echo "<h3>4. Available Transfer IDs</h3>";
    $stmt = $pdo->query("SELECT id, name, plate, status FROM transfers ORDER BY id DESC LIMIT 10");
    $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($transfers) {
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Plate</th><th>Status</th><th>Test Link</th></tr>";
        foreach ($transfers as $t) {
            echo "<tr>";
            echo "<td>{$t['id']}</td>";
            echo "<td>{$t['name']}</td>";
            echo "<td>{$t['plate']}</td>";
            echo "<td>{$t['status']}</td>";
            echo "<td><a href='public_view.php?id={$t['id']}' target='_blank'>Open</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test 5: Simulate API call
    echo "<h3>5. Simulating API call for ID: $test_id</h3>";
    $stmt = $pdo->prepare("SELECT id, name, plate, status, service_date as serviceDate, user_response as userResponse, review_stars as reviewStars, review_comment as reviewComment FROM transfers WHERE id = ?");
    $stmt->execute([$test_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        echo "✓ Transfer found!<br>";
        echo "<pre style='background: #f4f4f4; padding: 10px; border-radius: 5px;'>";
        echo json_encode($row, JSON_PRETTY_PRINT);
        echo "</pre>";
        echo "<p><strong>Test link:</strong> <a href='public_view.php?id=$test_id' target='_blank'>public_view.php?id=$test_id</a></p>";
    } else {
        echo "✗ Transfer with ID $test_id NOT found<br>";
        echo "<p style='color: red;'>This is the same error the customer would see.</p>";
    }
    
    // Test 6: Test the actual API endpoint
    echo "<h3>6. Testing actual API endpoint</h3>";
    $api_url = "http://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['PHP_SELF']) . "/api.php?action=get_public_transfer&id=$test_id";
    echo "API URL: <a href='$api_url' target='_blank'>$api_url</a><br>";
    
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Status: $httpCode<br>";
    echo "Response:<br>";
    echo "<pre style='background: #f4f4f4; padding: 10px; border-radius: 5px;'>";
    echo htmlspecialchars($response);
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
}
?>
