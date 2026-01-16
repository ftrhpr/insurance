<?php
/**
 * Debug script to check slug issues
 */

require_once '../config.php';

try {
    $pdo = getDBConnection();

    // Check if slug column exists
    $stmt = $pdo->query('DESCRIBE transfers');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasSlug = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'slug') {
            $hasSlug = true;
            break;
        }
    }

    echo 'Slug column exists: ' . ($hasSlug ? 'YES' : 'NO') . PHP_EOL;

    if ($hasSlug) {
        // Check if the specific slug exists
        $stmt = $pdo->prepare('SELECT id, name, plate, slug FROM transfers WHERE slug = ?');
        $stmt->execute(['404900737-vv810iv']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            echo 'Slug found in database:' . PHP_EOL;
            echo 'ID: ' . $result['id'] . PHP_EOL;
            echo 'Name: ' . $result['name'] . PHP_EOL;
            echo 'Plate: ' . $result['plate'] . PHP_EOL;
            echo 'Slug: ' . $result['slug'] . PHP_EOL;
        } else {
            echo 'Slug NOT found in database' . PHP_EOL;

            // Check recent invoices without slugs
            $stmt = $pdo->query('SELECT id, name, plate, slug FROM transfers WHERE slug IS NULL OR slug = "" ORDER BY id DESC LIMIT 5');
            $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo 'Recent invoices without slugs:' . PHP_EOL;
            foreach ($recent as $invoice) {
                echo 'ID: ' . $invoice['id'] . ', Name: ' . $invoice['name'] . ', Plate: ' . $invoice['plate'] . PHP_EOL;
            }
        }
    }

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>