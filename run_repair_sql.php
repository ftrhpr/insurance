<?php
// run_repair_sql.php - Execute the repair management SQL

require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to database successfully.<br><br>";

    // Read and execute the SQL file
    $sql = file_get_contents('add_repair_management.sql');

    if ($sql === false) {
        die("Could not read SQL file.<br>");
    }

    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Executing: " . substr($statement, 0, 50) . "...<br>";
            $pdo->exec($statement);
            echo "✓ Success<br>";
        }
    }

    echo "<br><strong>All repair management columns added successfully!</strong><br>";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}
?>