<?php
/**
 * SMS Templates Workflow Enhancement
 * Adds workflow stage binding to SMS templates
 */

header('Content-Type: text/plain');

// --- CONFIGURATION (Matches api.php) ---
$db_host = 'localhost';
$db_name = 'otoexpre_userdb';
$db_user = 'otoexpre_userdb';
$db_pass = 'p52DSsthB}=0AeZ#';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to database successfully.\n\n";

    // Check if workflow_stages column exists, add it if not
    echo "Checking SMS templates table structure...\n";

    $columns = $pdo->query("SHOW COLUMNS FROM sms_templates")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');

    if (!in_array('workflow_stages', $columnNames)) {
        echo "Adding workflow_stages column...\n";
        $pdo->exec("ALTER TABLE sms_templates ADD COLUMN workflow_stages JSON DEFAULT NULL COMMENT 'Array of workflow stages this template applies to'");
        echo "✓ Added workflow_stages column\n";
    } else {
        echo "✓ workflow_stages column already exists\n";
    }

    if (!in_array('is_active', $columnNames)) {
        echo "Adding is_active column...\n";
        $pdo->exec("ALTER TABLE sms_templates ADD COLUMN is_active BOOLEAN DEFAULT 1 COMMENT 'Whether this template is active'");
        echo "✓ Added is_active column\n";
    } else {
        echo "✓ is_active column already exists\n";
    }

    if (!in_array('created_at', $columnNames)) {
        echo "Adding created_at column...\n";
        $pdo->exec("ALTER TABLE sms_templates ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "✓ Added created_at column\n";
    } else {
        echo "✓ created_at column already exists\n";
    }

    if (!in_array('updated_at', $columnNames)) {
        echo "Adding updated_at column...\n";
        $pdo->exec("ALTER TABLE sms_templates ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "✓ Added updated_at column\n";
    } else {
        echo "✓ updated_at column already exists\n";
    }

    // Set default workflow stage bindings for existing templates
    echo "\nSetting default workflow stage bindings...\n";

    $defaultBindings = [
        'registered' => ['Processing'], // Welcome SMS when entering Processing
        'schedule' => ['Processing', 'Scheduled'], // Schedule confirmation
        'called' => ['Called'], // Contact confirmation
        'parts_ordered' => ['Parts Ordered'], // Parts ordered notification
        'parts_arrived' => ['Parts Arrived'], // Parts arrived with link
        'rescheduled' => [], // Handled separately in reschedule logic
        'completed' => ['Completed'], // Service completion
        'issue' => ['Issue'], // Issue notification
        'system' => [] // System alerts, not workflow-bound
    ];

    foreach ($defaultBindings as $slug => $stages) {
        $stagesJson = json_encode($stages);
        $stmt = $pdo->prepare("UPDATE sms_templates SET workflow_stages = ?, is_active = 1 WHERE slug = ?");
        $stmt->execute([$stagesJson, $slug]);
        echo "✓ Set workflow stages for '$slug': " . implode(', ', $stages) . "\n";
    }

    // Create workflow_stages reference table for better data integrity
    echo "\nChecking workflow_stages reference table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS workflow_stages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        stage_name VARCHAR(50) UNIQUE NOT NULL,
        stage_order INT DEFAULT 0,
        description VARCHAR(255),
        is_active BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✓ Workflow stages table verified\n";

    // Insert default workflow stages
    $defaultStages = [
        ['New', 1, 'Initial case import - awaiting processing'],
        ['Processing', 2, 'Case is being reviewed and processed'],
        ['Called', 3, 'Customer has been contacted'],
        ['Parts Ordered', 4, 'Parts have been ordered'],
        ['Parts Arrived', 5, 'Parts have arrived, customer notified'],
        ['Scheduled', 6, 'Service date has been set'],
        ['Completed', 7, 'Service has been completed'],
        ['Issue', 8, 'There is an issue with the case']
    ];

    foreach ($defaultStages as $stage) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO workflow_stages (stage_name, stage_order, description) VALUES (?, ?, ?)");
        $stmt->execute($stage);
    }
    echo "✓ Default workflow stages inserted\n";

    echo "\n---------------------------------\n";
    echo "SMS Templates Workflow Enhancement Complete!\n";
    echo "---------------------------------\n";
    echo "New features:\n";
    echo "- Templates can be bound to workflow stages\n";
    echo "- Automatic SMS sending based on stage transitions\n";
    echo "- Template activation/deactivation\n";
    echo "- Workflow stage reference table\n";

} catch (PDOException $e) {
    echo "CRITICAL ERROR: " . $e->getMessage();
}
?>