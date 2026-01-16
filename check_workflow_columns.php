<?php
require_once 'config.php';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query('DESCRIBE transfers');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hasRepairStage = false;
    $hasRepairAssignments = false;

    foreach ($columns as $col) {
        if ($col['Field'] === 'repair_stage') $hasRepairStage = true;
        if ($col['Field'] === 'repair_assignments') $hasRepairAssignments = true;
    }

    echo "repair_stage column: " . ($hasRepairStage ? 'EXISTS' : 'MISSING') . "\n";
    echo "repair_assignments column: " . ($hasRepairAssignments ? 'EXISTS' : 'MISSING') . "\n";

    if (!$hasRepairStage || !$hasRepairAssignments) {
        echo "Running add_workflow_columns.sql...\n";
        $sql = file_get_contents('add_workflow_columns.sql');
        $pdo->exec($sql);
        echo "Columns added successfully.\n";
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>