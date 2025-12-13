<?php
// Import SMS templates from last version (English templates from language.php)
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<h2>SMS Templates Import Tool</h2>";
echo "<p>Importing English SMS templates from last version...</p>";
echo "<hr>";

// Database credentials
$db_host = 'localhost';
$db_name = 'otoexpre_userdb';
$db_user = 'otoexpre_userdb';
$db_pass = 'p52DSsthB}=0AeZ#';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Database connection successful<br><br>";

    // English SMS templates from last version (language.php)
    $englishTemplates = [
        'registered' => "Hello {name}, payment received. Ref: {plate}. Welcome to OTOMOTORS service.",
        'schedule' => "Hello {name}, your service is scheduled for {date}. Ref: {plate}. - OTOMOTORS",
        'parts_arrived' => "Hello {name}, parts arrived for {plate}. Confirm service: {link} - OTOMOTORS",
        'completed' => "Service for {plate} is completed. Thank you for choosing OTOMOTORS! Rate your experience: {link}",
        'reschedule_accepted' => "Hello {name}, your reschedule request has been approved! New appointment: {date}. Ref: {plate}. - OTOMOTORS",
        'called' => "Hello {name}, we contacted you regarding {plate}. Service details will follow shortly.",
        'contacted' => "Hello {name}, we have contacted you about your {plate} service. Please check your messages.",
        'parts_ordered' => "Parts ordered for {plate}. We will notify you when ready.",
        'rescheduled' => "Hello {name}, your service has been rescheduled to {date}. Please confirm: {link}",
        'issue' => "Hello {name}, we detected an issue with {plate}. Our team will contact you shortly.",
        'system' => "System Alert: {count} new transfer(s) added to OTOMOTORS portal."
    ];

    // Check current templates
    echo "<h3>Current Templates in Database:</h3>";
    $stmt = $pdo->query("SELECT slug, content FROM sms_templates ORDER BY slug");
    $currentTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($currentTemplates)) {
        echo "<p>No templates found. Importing all English templates...</p>";
    } else {
        echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Slug</th><th>Current Content</th></tr>";
        foreach ($currentTemplates as $template) {
            echo "<tr>";
            echo "<td><strong>{$template['slug']}</strong></td>";
            echo "<td style='max-width: 500px; word-wrap: break-word;'>" . htmlspecialchars($template['content']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<br><p>Updating existing templates and adding missing ones with English versions...</p>";
    }

    // Prepare statements
    $insertStmt = $pdo->prepare("INSERT INTO sms_templates (slug, content, workflow_stages, is_active) VALUES (?, ?, '[]', 1) ON DUPLICATE KEY UPDATE content = VALUES(content)");
    $updated = 0;
    $inserted = 0;

    echo "<h3>Import Results:</h3>";
    foreach ($englishTemplates as $slug => $content) {
        $insertStmt->execute([$slug, $content]);
        if ($insertStmt->rowCount() > 0) {
            $updated++;
            echo "✓ Updated template: <strong>$slug</strong><br>";
        } else {
            $inserted++;
            echo "✓ Inserted template: <strong>$slug</strong><br>";
        }
    }

    echo "<br><p style='color: green; font-weight: bold;'>✓ Import completed! Updated: $updated, Inserted: $inserted</p>";

    // Show final state
    echo "<br><hr><h3>Final Template State:</h3>";
    $stmt = $pdo->query("SELECT slug, content FROM sms_templates ORDER BY slug");
    $finalTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Slug</th><th>Content</th></tr>";
    foreach ($finalTemplates as $template) {
        echo "<tr>";
        echo "<td><strong>{$template['slug']}</strong></td>";
        echo "<td style='max-width: 500px; word-wrap: break-word;'>" . htmlspecialchars($template['content']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<br><p style='background: #e8f5e9; padding: 15px; border-left: 4px solid #4caf50;'>";
    echo "<strong>✓ Done!</strong> All SMS templates have been imported from the last version (English).<br>";
    echo "Templates are now in English and ready for use.";
    echo "</p>";

} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
}
?>