<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$id = 136;
$stmt = $pdo->prepare("SELECT id, plate, name, repair_parts, repair_labor FROM transfers WHERE id = ?");
$stmt->execute([$id]);
$case = $stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Case #136</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 15px; margin: 10px 0; border: 1px solid #ddd; }
        pre { background: #eee; padding: 10px; overflow-x: auto; }
        h2 { color: #333; }
    </style>
</head>
<body>
    <h1>Debug Case #<?= $id ?></h1>
    
    <div class="box">
        <h2>Basic Info</h2>
        <p><strong>Plate:</strong> <?= htmlspecialchars($case['plate']) ?></p>
        <p><strong>Name:</strong> <?= htmlspecialchars($case['name']) ?></p>
    </div>
    
    <div class="box">
        <h2>repair_parts (Raw from Database)</h2>
        <p><strong>Type:</strong> <?= gettype($case['repair_parts']) ?></p>
        <p><strong>Is NULL:</strong> <?= $case['repair_parts'] === null ? 'Yes' : 'No' ?></p>
        <p><strong>Is Empty String:</strong> <?= $case['repair_parts'] === '' ? 'Yes' : 'No' ?></p>
        <p><strong>Length:</strong> <?= strlen($case['repair_parts'] ?? '') ?></p>
        <p><strong>Raw Value:</strong></p>
        <pre><?= htmlspecialchars(var_export($case['repair_parts'], true)) ?></pre>
    </div>
    
    <div class="box">
        <h2>repair_labor (Raw from Database)</h2>
        <p><strong>Type:</strong> <?= gettype($case['repair_labor']) ?></p>
        <p><strong>Is NULL:</strong> <?= $case['repair_labor'] === null ? 'Yes' : 'No' ?></p>
        <p><strong>Is Empty String:</strong> <?= $case['repair_labor'] === '' ? 'Yes' : 'No' ?></p>
        <p><strong>Length:</strong> <?= strlen($case['repair_labor'] ?? '') ?></p>
        <p><strong>Raw Value:</strong></p>
        <pre><?= htmlspecialchars(var_export($case['repair_labor'], true)) ?></pre>
    </div>
    
    <div class="box">
        <h2>Decoded repair_parts</h2>
        <?php 
        $decoded_parts = json_decode($case['repair_parts'] ?? '[]', true);
        ?>
        <p><strong>Decoded Type:</strong> <?= gettype($decoded_parts) ?></p>
        <p><strong>Is Array:</strong> <?= is_array($decoded_parts) ? 'Yes' : 'No' ?></p>
        <p><strong>Count:</strong> <?= is_array($decoded_parts) ? count($decoded_parts) : 0 ?></p>
        <pre><?= htmlspecialchars(print_r($decoded_parts, true)) ?></pre>
    </div>
    
    <div class="box">
        <h2>Decoded repair_labor</h2>
        <?php 
        $decoded_labor = json_decode($case['repair_labor'] ?? '[]', true);
        ?>
        <p><strong>Decoded Type:</strong> <?= gettype($decoded_labor) ?></p>
        <p><strong>Is Array:</strong> <?= is_array($decoded_labor) ? 'Yes' : 'No' ?></p>
        <p><strong>Count:</strong> <?= is_array($decoded_labor) ? count($decoded_labor) : 0 ?></p>
        <pre><?= htmlspecialchars(print_r($decoded_labor, true)) ?></pre>
    </div>
</body>
</html>
