<?php
session_start();
require_once 'session_config.php';
require_once 'config.php';

// Restrict access to admin users only for safety
if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin'])) {
    http_response_code(403);
    echo "<h2>Forbidden</h2><p>Admin access required.</p>";
    exit;
}

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Workflow Health Check</h1>";
$results = [];

// Show configured PHP error_log location
$errorLog = ini_get('error_log');
$results['error_log_path'] = $errorLog ?: '(no error_log configured)';

// Attempt to write a test message to the PHP error log
$testMsg = '[workflow_health] test log at ' . date('c');
if (@error_log($testMsg)) {
    $results['write_test_log'] = 'ok';
} else {
    $results['write_test_log'] = 'failed';
}

// Try to read last lines of the log (if path exists and readable)
$results['recent_logs'] = null;
if ($errorLog && is_readable($errorLog)) {
    $lines = @file($errorLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        $last = array_slice($lines, -50);
        $results['recent_logs'] = $last;
    }
}

// DB connection check
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $results['db'] = 'connected';

    // Check required columns on transfers table
    $required = ['repair_stage', 'repair_assignments', 'stage_timers', 'stage_statuses'];
    $missing = [];
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'transfers'");
    $stmt->execute([DB_NAME]);
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($required as $col) {
        if (!in_array($col, $cols)) $missing[] = $col;
    }
    $results['missing_columns'] = $missing;
} catch (Exception $e) {
    $results['db'] = 'error: ' . $e->getMessage();
}

// Output results in a simple HTML report
echo "<h2>Summary</h2>";
echo "<ul>";
echo "<li><strong>PHP error_log</strong>: " . htmlspecialchars($results['error_log_path']) . "</li>";
echo "<li><strong>Write test</strong>: " . htmlspecialchars($results['write_test_log']) . "</li>";
echo "<li><strong>DB</strong>: " . htmlspecialchars($results['db']) . "</li>";
echo "<li><strong>Missing transfers columns</strong>: " . (empty($results['missing_columns']) ? '<span style=\"color:green\">None</span>' : '<span style=\"color:orange\">'.htmlspecialchars(implode(', ', $results['missing_columns'])).'</span>') . "</li>";
echo "</ul>";

if (!empty($results['recent_logs'])) {
    echo "<h3>Recent server logs (last 50 lines)</h3>\n<pre style=\"background:#111;color:#eee;padding:10px;max-height:400px;overflow:auto\">" . htmlspecialchars(implode("\n", $results['recent_logs'])) . "</pre>";
} else {
    echo "<p>No readable server log available or not configured.</p>";
}

if (!empty($results['missing_columns'])) {
    echo "<h3>Next steps</h3>";
    echo "<ol><li>Run <code>fix_db_all.php</code> on the server to add missing columns (or apply the equivalent ALTER TABLE).</li>";
    echo "<li>Check file permissions and PHP error log path (above) to ensure PHP can write logs.</li></ol>";
}

echo "<p><a href=\"workflow.php\">Open workflow page</a></p>";

?>