<?php
// fix_parts_nulls.php
// Backup and normalize NULL `parts` column values to empty JSON array '[]'
require_once 'config.php';

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    echo "DB connection failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo "Checking transfers for NULL parts...\n";
$stmt = $pdo->query("SELECT id, plate FROM transfers WHERE parts IS NULL");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$count = count($rows);
if ($count === 0) {
    echo "No transfers with NULL parts found. Nothing to do.\n";
    exit(0);
}

echo "Found $count transfers with NULL parts. Backing up list...\n";
$backupFile = __DIR__ . '/backups/parts_nulls_backup_' . date('Ymd_His') . '.json';
@mkdir(dirname($backupFile), 0755, true);
file_put_contents($backupFile, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Backup written to: $backupFile\n";

// Update rows to empty JSON array
try {
    $pdo->beginTransaction();
    $upd = $pdo->prepare("UPDATE transfers SET parts = ? WHERE id = ?");
    foreach ($rows as $r) {
        $upd->execute([json_encode([]), $r['id']]);
    }
    $pdo->commit();
    echo "Updated $count rows to empty JSON array [].\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Failed to update rows: " . $e->getMessage() . PHP_EOL;
    echo "You can inspect backup file and retry manually." . PHP_EOL;
    exit(1);
}

echo "Done. Please re-run test_parts_smoketest.php to confirm values.\n";

?>
