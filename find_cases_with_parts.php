<?php
require_once 'config.php';

// Get database connection
try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Find cases with parts or services
$query = "SELECT id, plate, name, 
    CASE 
        WHEN repair_parts IS NOT NULL AND repair_parts != '' AND repair_parts != '[]' THEN 'Yes'
        ELSE 'No'
    END as has_parts,
    CASE 
        WHEN repair_labor IS NOT NULL AND repair_labor != '' AND repair_labor != '[]' THEN 'Yes'
        ELSE 'No'
    END as has_labor
FROM transfers 
WHERE (repair_parts IS NOT NULL AND repair_parts != '' AND repair_parts != '[]')
   OR (repair_labor IS NOT NULL AND repair_labor != '' AND repair_labor != '[]')
LIMIT 20";

$stmt = $pdo->query($query);
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cases with Parts/Services</title>
    <style>
        body { font-family: system-ui; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f4f4f4; font-weight: bold; }
        .yes { color: green; font-weight: bold; }
        .no { color: #999; }
    </style>
</head>
<body>
    <h1>Cases with Parts or Services</h1>
    <?php if (count($cases) > 0): ?>
        <p>Found <?= count($cases) ?> cases with parts or services data:</p>
        <table>
            <tr>
                <th>Case ID</th>
                <th>Plate</th>
                <th>Customer</th>
                <th>Has Parts</th>
                <th>Has Services</th>
            </tr>
            <?php foreach ($cases as $case): ?>
            <tr>
                <td><strong>#<?= $case['id'] ?></strong></td>
                <td><?= htmlspecialchars($case['plate']) ?></td>
                <td><?= htmlspecialchars($case['name']) ?></td>
                <td class="<?= strtolower($case['has_parts']) ?>"><?= $case['has_parts'] ?></td>
                <td class="<?= strtolower($case['has_labor']) ?>"><?= $case['has_labor'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p style="margin-top: 20px; color: #666;">
            ✅ Test Quick View with any of these case IDs to see parts/services display working
        </p>
    <?php else: ?>
        <p style="color: #f00;">❌ No cases found with parts or services data.</p>
        <p>You need to add parts/services to a case first through edit_case.php</p>
    <?php endif; ?>
</body>
</html>
