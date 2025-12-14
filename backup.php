<?php
// backup.php - Create a full backup of the OTOMOTORS project
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== OTOMOTORS Project Backup Tool ===\n";
echo "Starting backup process...\n\n";

// Configuration
$projectDir = __DIR__;
$backupDir = $projectDir . '/backups';
$timestamp = date('Y-m-d_H-i-s');
$backupName = "otomotors_backup_{$timestamp}";
$backupPath = $backupDir . '/' . $backupName;

// Create backups directory if it doesn't exist
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
    echo "Created backups directory: $backupDir\n";
}

// Database backup
echo "1. Backing up database...\n";
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    $sqlContent = "-- OTOMOTORS Database Backup\n";
    $sqlContent .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
    $sqlContent .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $table) {
        echo "   Exporting table: $table\n";

        // Get table structure
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
        $sqlContent .= $createTable['Create Table'] . ";\n\n";

        // Get table data
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
            $columnList = '`' . implode('`, `', $columns) . '`';

            foreach ($rows as $row) {
                $values = array_map(function($value) use ($pdo) {
                    if ($value === null) return 'NULL';
                    return $pdo->quote($value);
                }, $row);

                $sqlContent .= "INSERT INTO `$table` ($columnList) VALUES (" . implode(', ', $values) . ");\n";
            }
            $sqlContent .= "\n";
        }
    }

    $sqlContent .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    // Save database backup
    $dbBackupFile = $backupPath . '_db.sql';
    file_put_contents($dbBackupFile, $sqlContent);
    echo "   Database backup saved to: $dbBackupFile\n";

} catch (Exception $e) {
    echo "   ERROR: Database backup failed: " . $e->getMessage() . "\n";
    echo "   Continuing with file backup only...\n";
}

// File backup
echo "\n2. Backing up project files...\n";

// Files and directories to exclude
$exclude = [
    '.git',
    'node_modules',
    'backups',
    '*.log',
    'error_log',
    '.DS_Store',
    'Thumbs.db'
];

$excludePatterns = array_map(function($pattern) {
    return "--exclude='$pattern'";
}, $exclude);

// Create tar command
$tarCommand = "tar -czf {$backupPath}.tar.gz " . implode(' ', $excludePatterns) . " -C " . dirname($projectDir) . " " . basename($projectDir);

echo "   Running: $tarCommand\n";
exec($tarCommand, $output, $returnCode);

if ($returnCode === 0) {
    echo "   File backup created: {$backupPath}.tar.gz\n";

    // Add database backup to the tarball if it exists
    if (file_exists($dbBackupFile)) {
        $addDbCommand = "tar -rf {$backupPath}.tar.gz -C " . dirname($dbBackupFile) . " " . basename($dbBackupFile);
        exec($addDbCommand, $output2, $returnCode2);
        if ($returnCode2 === 0) {
            echo "   Database backup added to tarball\n";
            unlink($dbBackupFile); // Remove the separate SQL file
        }
    }

    // Calculate backup size
    $backupSize = filesize("{$backupPath}.tar.gz");
    $sizeFormatted = round($backupSize / 1024 / 1024, 2) . ' MB';
    echo "   Backup size: $sizeFormatted\n";

    echo "\n✅ Backup completed successfully!\n";
    echo "   Backup file: {$backupPath}.tar.gz\n";
    echo "   To restore, run: php restore.php {$backupName}.tar.gz\n";

} else {
    echo "   ERROR: File backup failed with code $returnCode\n";
    echo "   Output: " . implode("\n", $output) . "\n";
}

// Create a simple restore script if it doesn't exist
$restoreScript = $projectDir . '/restore.php';
if (!file_exists($restoreScript)) {
    $restoreContent = '<?php
// restore.php - Restore OTOMOTORS project from backup
if ($argc < 2) {
    echo "Usage: php restore.php <backup_file.tar.gz>\n";
    exit(1);
}

$backupFile = $argv[1];
if (!file_exists($backupFile)) {
    echo "ERROR: Backup file not found: $backupFile\n";
    exit(1);
}

echo "=== OTOMOTORS Project Restore Tool ===\n";
echo "Restoring from: $backupFile\n\n";

// Extract backup
$extractCommand = "tar -xzf $backupFile -C " . dirname(__DIR__);
echo "Extracting files...\n";
exec($extractCommand, $output, $returnCode);

if ($returnCode !== 0) {
    echo "ERROR: Extraction failed\n";
    exit(1);
}

// Restore database if SQL file exists in backup
$dbBackupFile = "otomotors_backup_*_db.sql";
$files = glob($dbBackupFile);
if (!empty($files)) {
    echo "\nRestoring database...\n";
    require_once "config.php";
    
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = file_get_contents($files[0]);
        $pdo->exec($sql);
        echo "Database restored successfully\n";
        unlink($files[0]); // Clean up
    } catch (Exception $e) {
        echo "ERROR: Database restore failed: " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Restore completed!\n";
?>';
    file_put_contents($restoreScript, $restoreContent);
    echo "\nCreated restore script: restore.php\n";
}

echo "\n=== Backup Summary ===\n";
echo "Backup created: {$backupPath}.tar.gz\n";
echo "Restore command: php restore.php {$backupName}.tar.gz\n";
echo "=======================\n";
?>