<?php
// tools/fx.php - Basic backup & restore tool for the workspace
// Usage:
//  php tools/fx.php backup [prefix]
//  php tools/fx.php restore [prefix|filename]
//  php tools/fx.php list [prefix]

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

$script = array_shift($argv);
$action = array_shift($argv) ?? 'help';
$arg = $argv[0] ?? null;

$repoRoot = realpath(__DIR__ . '/..');
$backupsDir = $repoRoot . DIRECTORY_SEPARATOR . 'backups';
if (!is_dir($backupsDir)) mkdir($backupsDir, 0775, true);

// Which file patterns to include in backup
$includePatterns = [
    '\.php$',
    '\.sql$',
    '\.json$',
    '\.js$',
    '\.css$',
    '\.html$',
    '\.md$',
    '\.txt$'
];
// if ZipArchive extension missing, print a clear message
if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "Error: PHP Zip extension required for backup/restore tool.\n");
    exit(1);
}

function listBackupFiles($dir, $prefix = 'fx') {
    $files = glob($dir . DIRECTORY_SEPARATOR . $prefix . '-*.zip');
    usort($files, function($a, $b){return filemtime($b) - filemtime($a);});
    return $files;
}

switch ($action) {
    case 'help':
        echo "Usage:\n";
        echo "  php tools/fx.php backup [prefix]   - create backup (default prefix 'fx')\n";
        echo "  php tools/fx.php list [prefix]     - list backups (default prefix 'fx')\n";
        echo "  php tools/fx.php restore <name|latest|prefix> - restore a backup\n";
        exit(0);
    case 'list':
        $prefix = $arg ?: 'fx';
        $files = listBackupFiles($backupsDir, $prefix);
        if (empty($files)) {
            echo "No backups found with prefix '{$prefix}' in {$backupsDir}\n";
            exit(0);
        }
        foreach ($files as $f) {
            echo basename($f) . " - " . date('Y-m-d H:i:s', filemtime($f)) . "\n";
        }
        exit(0);
    case 'backup':
        $prefix = $arg ?: 'fx';
        $now = date('Ymd-His');
        $zipName = $prefix . '-' . $now . '.zip';
        $zipPath = $backupsDir . DIRECTORY_SEPARATOR . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            echo "Failed to create zip file: $zipPath\n";
            exit(1);
        }

        $root = realpath($repoRoot) . DIRECTORY_SEPARATOR;
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
        $count = 0;
        foreach ($items as $item) {
            $filePath = $item->getPathname();
            $rel = substr($filePath, strlen($root));
            if (strpos($rel, 'backups' . DIRECTORY_SEPARATOR) === 0) continue; // skip backups folder
            if (strpos($rel, '.git' . DIRECTORY_SEPARATOR) === 0) continue; // skip .git

            // Match include patterns only and avoid adding large binaries
            foreach ($includePatterns as $pattern) {
                if (preg_match("/{$pattern}/i", $filePath)) {
                    if (filesize($filePath) > (5 * 1024 * 1024)) continue; // skip files >5MB
                    $zip->addFile($filePath, $rel);
                    $count++;
                    break;
                }
            }
        }
        $zip->close();
        echo "Backup created: {$zipPath} (files added: {$count})\n";
        exit(0);
    case 'restore':
        $param = $arg ?: 'fx';
        $prefix = 'fx';
        $targetZip = null;
        // If param looks like a file name, use it; if 'fx' or 'latest', pick latest.
        $candidate = $backupsDir . DIRECTORY_SEPARATOR . $param;
        if ($param === 'latest') {
            $files = listBackupFiles($backupsDir, $prefix);
            if (empty($files)) {
                echo "No backups found to restore\n";
                exit(1);
            }
            $targetZip = $files[0];
        } elseif (file_exists($candidate)) {
            $targetZip = $candidate;
        } else {
            // wildcard: param is prefix, find latest with that prefix
            $files = listBackupFiles($backupsDir, $param);
            if (empty($files)) {
                echo "No backups found for prefix '{$param}' in {$backupsDir}\n";
                exit(1);
            }
            $targetZip = $files[0];
        }

        if (!file_exists($targetZip)) {
            echo "Backup file not found: {$targetZip}\n";
            exit(1);
        }

        echo "Restoring backup: " . basename($targetZip) . "\n";

        // Unzip into repo root; overwrite existing files
        $zip = new ZipArchive();
        if ($zip->open($targetZip) !== true) {
            echo "Failed to open backup: {$targetZip}\n";
            exit(1);
        }

        for ($i=0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            $targetPath = $repoRoot . DIRECTORY_SEPARATOR . $entry;
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) mkdir($targetDir, 0775, true);
            copy('zip://' . $targetZip . '#' . $entry, $targetPath);
            echo "Restored: {$entry}\n";
        }
        $zip->close();
        echo "Restore complete.\n";
        exit(0);
    default:
        echo "Unknown action: {$action}\n";
        echo "Run with 'backup', 'restore', or 'list'.\n";
        exit(1);
}
