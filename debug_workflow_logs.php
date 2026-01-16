<?php
session_start();
require_once 'session_config.php';
require_once 'config.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin'])) {
    http_response_code(403);
    echo "Forbidden: admin only.";
    exit;
}

header('Content-Type: text/html; charset=utf-8');

$root = __DIR__;
$workflowLog = $root . '/workflow_exec.log';
$errorLog = ini_get('error_log') ?: ($root . '/error_log');

function readLastLines($file, $n = 200) {
    if (!is_readable($file)) return null;
    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return null;
    return array_slice($lines, -$n);
}

echo "<h1>Debug: workflow logs</h1>";
echo "<p>workflow_exec.log: <code>" . htmlspecialchars($workflowLog) . "</code></p>";
echo "<p>php error_log: <code>" . htmlspecialchars($errorLog) . "</code></p>";

$w = readLastLines($workflowLog, 200);
$e = readLastLines($errorLog, 200);

if ($w !== null) {
    echo "<h2>workflow_exec.log (last " . count($w) . " lines)</h2><pre style='background:#111;color:#fff;padding:10px;max-height:400px;overflow:auto'>" . htmlspecialchars(implode("\n", $w)) . "</pre>";
} else {
    echo "<p>workflow_exec.log not found or not readable.</p>";
}

if ($e !== null) {
    echo "<h2>php error_log (last " . count($e) . " lines)</h2><pre style='background:#111;color:#fff;padding:10px;max-height:400px;overflow:auto'>" . htmlspecialchars(implode("\n", $e)) . "</pre>";
} else {
    echo "<p>php error_log not found or not readable.</p>";
}

echo "<p><a href='workflow.php'>Open workflow</a></p>";
?>