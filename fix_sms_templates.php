<?php
// Fix SMS templates to use correct parameter name 'id' instead of 'order_id'
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<h2>SMS Template Fix Tool</h2>";
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
    
    // Check if sms_templates table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'sms_templates'");
    if ($stmt->rowCount() == 0) {
        echo "✗ Table 'sms_templates' does not exist yet.<br>";
        echo "<p>Creating table...</p>";
        $pdo->exec("CREATE TABLE IF NOT EXISTS sms_templates (
            slug VARCHAR(50) PRIMARY KEY,
            content TEXT
        )");
        echo "✓ Table created<br>";
    }
    
    // Get current templates
    echo "<h3>Current Templates:</h3>";
    $stmt = $pdo->query("SELECT slug, content FROM sms_templates");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($templates)) {
        echo "<p>No templates found in database. Inserting default templates...</p>";
        
        $defaultTemplates = [
            'registered' => "Hello {name}, payment received. Ref: {plate}. Welcome to OTOMOTORS service.",
            'schedule' => "Hello {name}, service scheduled for {date}. Ref: {plate}.",
            'parts_ordered' => "Parts ordered for {plate}. We will notify you when ready.",
            'parts_arrived' => "Hello {name}, your parts have arrived! Please confirm your visit here: {link}",
            'rescheduled' => "Hello {name}, your service has been rescheduled to {date}. Please confirm: {link}",
            'completed' => "Service for {plate} is completed. Rate your experience: {link}"
        ];
        
        $stmt = $pdo->prepare("INSERT INTO sms_templates (slug, content) VALUES (?, ?)");
        foreach ($defaultTemplates as $slug => $content) {
            $stmt->execute([$slug, $content]);
            echo "✓ Inserted template: <strong>$slug</strong><br>";
        }
        
        echo "<p style='color: green;'>✓ All default templates inserted!</p>";
        
    } else {
        echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Slug</th><th>Current Content</th><th>Contains order_id?</th><th>Action</th></tr>";
        
        $hasIssues = false;
        foreach ($templates as $template) {
            $hasOrderId = strpos($template['content'], 'order_id') !== false;
            if ($hasOrderId) $hasIssues = true;
            
            echo "<tr>";
            echo "<td><strong>{$template['slug']}</strong></td>";
            echo "<td style='max-width: 400px; word-wrap: break-word;'>" . htmlspecialchars($template['content']) . "</td>";
            echo "<td style='text-align: center; color: " . ($hasOrderId ? 'red' : 'green') . ";'>";
            echo $hasOrderId ? "❌ YES" : "✓ NO";
            echo "</td>";
            echo "<td>" . ($hasOrderId ? "NEEDS FIX" : "OK") . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        if ($hasIssues) {
            echo "<br><h3>Fixing templates...</h3>";
            
            $stmt = $pdo->prepare("UPDATE sms_templates SET content = ? WHERE slug = ?");
            $fixed = 0;
            
            foreach ($templates as $template) {
                if (strpos($template['content'], 'order_id') !== false) {
                    $newContent = str_replace('order_id=', 'id=', $template['content']);
                    $stmt->execute([$newContent, $template['slug']]);
                    $fixed++;
                    echo "✓ Fixed template: <strong>{$template['slug']}</strong><br>";
                    echo "  Old: " . htmlspecialchars($template['content']) . "<br>";
                    echo "  New: " . htmlspecialchars($newContent) . "<br><br>";
                }
            }
            
            if ($fixed > 0) {
                echo "<p style='color: green; font-weight: bold;'>✓ Fixed $fixed template(s)!</p>";
            }
        } else {
            echo "<br><p style='color: green;'>✓ All templates are correct - no 'order_id' found!</p>";
        }
    }
    
    // Show final state
    echo "<br><hr><h3>Final Template State:</h3>";
    $stmt = $pdo->query("SELECT slug, content FROM sms_templates");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Slug</th><th>Content</th></tr>";
    foreach ($templates as $template) {
        echo "<tr>";
        echo "<td><strong>{$template['slug']}</strong></td>";
        echo "<td style='max-width: 500px; word-wrap: break-word;'>" . htmlspecialchars($template['content']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><p style='background: #e8f5e9; padding: 15px; border-left: 4px solid #4caf50;'>";
    echo "<strong>✓ Done!</strong> All SMS templates now use 'id=' instead of 'order_id='<br>";
    echo "Links will now be in format: <code>public_view.php?id=123</code>";
    echo "</p>";
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
}
?>
