<?php
/**
 * Generate slugs for existing invoices that don't have them
 * Run this script once to populate slugs for all existing invoices
 */

define('API_ACCESS', true);
require_once 'config.php';

// Verify API key (optional for this maintenance script)
if (isset($_GET['key']) && $_GET['key'] !== API_KEY) {
    die('Unauthorized');
}

try {
    // Get database connection
    $pdo = getDBConnection();

    // Find all invoices without slugs
    $sql = "SELECT id, name, phone, plate, vehicle_make, vehicle_model FROM transfers WHERE slug IS NULL OR slug = ''";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $updated = 0;

    echo "Found " . count($invoices) . " invoices without slugs\n";

    foreach ($invoices as $invoice) {
        // Generate slug for this invoice
        $slug = generateUniqueSlug($pdo, $invoice['name'] ?? 'customer', $invoice['plate'] ?? '');

        // Update the invoice with the slug
        $updateSql = "UPDATE transfers SET slug = :slug WHERE id = :id";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            ':slug' => $slug,
            ':id' => $invoice['id']
        ]);

        echo "Updated invoice ID {$invoice['id']} with slug: {$slug}\n";
        $updated++;
    }

    echo "Successfully updated {$updated} invoices with slugs\n";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Generate a unique slug for public invoice sharing
 * @param PDO $pdo Database connection
 * @param string $customerName Customer name
 * @param string $plate License plate
 * @return string Unique slug
 */
function generateUniqueSlug($pdo, $customerName, $plate) {
    // Clean and prepare base slug
    $baseSlug = strtolower(trim($customerName . '-' . $plate));
    $baseSlug = preg_replace('/[^a-z0-9\-]/', '-', $baseSlug);
    $baseSlug = preg_replace('/-+/', '-', $baseSlug);
    $baseSlug = trim($baseSlug, '-');

    // If base slug is empty, use a random string
    if (empty($baseSlug)) {
        $baseSlug = 'invoice-' . substr(md5(uniqid()), 0, 8);
    }

    $slug = $baseSlug;
    $counter = 1;

    // Ensure uniqueness
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM transfers WHERE slug = :slug");
        $stmt->execute([':slug' => $slug]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] == 0) {
            break; // Slug is unique
        }

        // Append counter and try again
        $slug = $baseSlug . '-' . $counter;
        $counter++;

        // Prevent infinite loop
        if ($counter > 1000) {
            $slug = $baseSlug . '-' . time() . '-' . rand(100, 999);
            break;
        }
    }

    return $slug;
}
?>