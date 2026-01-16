<?php
// Lightweight probe to diagnose why workflow.php returns HTTP 500
// Accessible to admins only
session_start();
require_once 'session_config.php';
if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin'])) {
    http_response_code(403);
    echo "Forbidden - admin only";
    exit;
}

function probe_log($m) {
    $f = sys_get_temp_dir() . '/workflow_probe.log';
    @file_put_contents($f, '['.date('c').'] '.$m."\n", FILE_APPEND|LOCK_EX);
    error_log('[workflow_probe] '.$m);
}

header('Content-Type: application/json');
$out = ['ok' => false, 'steps' => []];

// Step: basic PHP working
probe_log('probe start');
$out['steps'][] = 'probe start';

// Step: check workflow.php readability
$wf = __DIR__ . '/workflow.php';
$out['workflow_exists'] = file_exists($wf);
$out['workflow_readable'] = is_readable($wf);
$out['workflow_size'] = $out['workflow_exists'] ? filesize($wf) : null;
probe_log('file_exists='.($out['workflow_exists']?1:0).', readable='.($out['workflow_readable']?1:0));

// Step: token parse
if ($out['workflow_exists'] && $out['workflow_readable']) {
    $code = file_get_contents($wf);
    try {
        $tokens = token_get_all($code);
        $out['token_count'] = is_array($tokens) ? count($tokens) : 0;
        probe_log('tokenized workflow.php, tokens='.count($tokens));
    } catch (Throwable $e) {
        $out['token_error'] = $e->getMessage();
        probe_log('tokenize failed: '.$e->getMessage());
    }
}

// Step: check includes for session_config.php and config.php
foreach (['session_config.php','config.php','language.php'] as $inc) {
    $p = __DIR__ . '/' . $inc;
    $out['includes'][$inc] = ['exists' => file_exists($p), 'readable' => is_readable($p)];
    probe_log("include check $inc exists=".($out['includes'][$inc]['exists']?1:0)." readable=".($out['includes'][$inc]['readable']?1:0));
}

// Step: try require config and open DB connection in try/catch
try {
    probe_log('attempting to require config.php');
    require_once __DIR__ . '/config.php';
    $out['config_loaded'] = true;
    if (defined('DB_HOST')) {
        probe_log('DB_HOST defined');
        $out['db_constants'] = ['DB_HOST'=>DB_HOST, 'DB_NAME'=>DB_NAME, 'DB_USER'=>DB_USER];
        try {
            $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $out['db_connect'] = true;
            probe_log('db connect ok');
        } catch (Throwable $e) {
            $out['db_connect'] = false;
            $out['db_error'] = $e->getMessage();
            probe_log('db connect failed: '.$e->getMessage());
        }
    } else {
        $out['db_constants'] = 'DB_HOST not defined';
        probe_log('DB_HOST not defined');
    }
} catch (Throwable $e) {
    $out['config_error'] = $e->getMessage();
    probe_log('require config failed: '.$e->getMessage());
}

// Step: attempt to run workflow.php in sandboxed include using buffer (no output expected)
try {
    probe_log('attempting to include workflow.php inside sandbox');
    ob_start();
    include __DIR__ . '/workflow.php';
    $content = ob_get_clean();
    $out['include_output_len'] = strlen($content);
    probe_log('include produced length='.strlen($content));
    // Don't return whole content, but note if content starts with < or contains 'Internal Server Error'
    $out['include_preview'] = substr($content,0,200);
} catch (Throwable $e) {
    $out['include_error'] = $e->getMessage();
    probe_log('include failed: '.$e->getMessage());
}

// Step: run php -l (lint) to get parse errors with file/line if possible
$lintResult = null;
$phpBinary = 'php';
if (function_exists('exec')) {
    $cmd = escapeshellcmd($phpBinary) . ' -l ' . escapeshellarg(__DIR__ . '/workflow.php') . ' 2>&1';
    probe_log('running lint: ' . $cmd);
    @exec($cmd, $lines, $rc);
    $lintResult = ['rc' => $rc, 'output' => $lines];
} else if (function_exists('shell_exec')) {
    $cmd = escapeshellcmd($phpBinary) . ' -l ' . escapeshellarg(__DIR__ . '/workflow.php') . ' 2>&1';
    probe_log('running lint via shell_exec: ' . $cmd);
    $outStr = @shell_exec($cmd);
    $lintResult = ['rc' => null, 'output' => explode("\n", trim($outStr))];
} else {
    $lintResult = ['error' => 'no exec/shell_exec available'];
}
$out['php_lint'] = $lintResult;


$out['ok'] = true;
echo json_encode($out, JSON_PRETTY_PRINT);
