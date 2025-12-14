<?php
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
    echo "Output: " . implode("\n", $output) . "\n";
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
?>