<?php
// Check SMS templates and workflow setup
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<h2>SMS Templates Status Check</h2>";
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

    // Check sms_templates table
    echo "<h3>SMS Templates Table:</h3>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'sms_templates'");
    if ($stmt->rowCount() == 0) {
        echo "❌ sms_templates table does not exist<br>";
    } else {
        echo "✓ sms_templates table exists<br>";

        // Check columns
        $columns = $pdo->query("SHOW COLUMNS FROM sms_templates")->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'Field');
        echo "Columns: " . implode(', ', $columnNames) . "<br><br>";

        // Show templates
        $stmt = $pdo->query("SELECT slug, content, workflow_stages, is_active FROM sms_templates ORDER BY slug");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($templates)) {
            echo "❌ No templates found in database<br>";
        } else {
            echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>Slug</th><th>Content</th><th>Workflow Stages</th><th>Active</th></tr>";
            foreach ($templates as $template) {
                $stages = json_decode($template['workflow_stages'] ?? '[]', true);
                $stagesText = is_array($stages) ? implode(', ', $stages) : 'None';
                echo "<tr>";
                echo "<td><strong>{$template['slug']}</strong></td>";
                echo "<td style='max-width: 300px; word-wrap: break-word;'>" . htmlspecialchars(substr($template['content'], 0, 100)) . "...</td>";
                echo "<td>{$stagesText}</td>";
                echo "<td>" . ($template['is_active'] ? '✓' : '❌') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }

    echo "<br><hr><h3>Workflow Stages Table:</h3>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'workflow_stages'");
    if ($stmt->rowCount() == 0) {
        echo "❌ workflow_stages table does not exist<br>";
        echo "<p style='color: red;'>⚠️  You need to run enhance_sms_workflow.php to set up the workflow system!</p>";
    } else {
        echo "✓ workflow_stages table exists<br>";

        $stmt = $pdo->query("SELECT stage_name, description, stage_order FROM workflow_stages WHERE is_active = 1 ORDER BY stage_order");
        $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($stages)) {
            echo "❌ No active workflow stages found<br>";
        } else {
            echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>Stage Name</th><th>Description</th><th>Order</th></tr>";
            foreach ($stages as $stage) {
                echo "<tr>";
                echo "<td><strong>{$stage['stage_name']}</strong></td>";
                echo "<td>{$stage['description']}</td>";
                echo "<td>{$stage['stage_order']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }

    echo "<br><hr><h3>Recommendations:</h3>";
    echo "<ol>";
    echo "<li>If workflow_stages table doesn't exist: Run <code>enhance_sms_workflow.php</code></li>";
    echo "<li>If no templates exist: Run <code>import_sms_templates.php</code></li>";
    echo "<li>Access <code>templates.php</code> to manage SMS templates</li>";
    echo "</ol>";

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}
?>