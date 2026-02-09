<?php
/**
 * OTOMOTORS Database Repair Tool
 * Checks and fixes all required tables and columns.
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

    // ---------------------------------------------------------
    // 1. TABLE: transfers
    // ---------------------------------------------------------
    echo "Checking table 'transfers'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS transfers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plate VARCHAR(20),
        name VARCHAR(100),
        amount DECIMAL(10,2),
        status VARCHAR(50) DEFAULT 'New',
        phone VARCHAR(20),
        franchise VARCHAR(50),
        rawText TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);

    // List of required columns for 'transfers'
    $columns = [
        'user_response' => "VARCHAR(50) DEFAULT 'Pending'",
        'service_date'  => "DATETIME DEFAULT NULL",
        'reschedule_date' => "DATETIME DEFAULT NULL",
        'reschedule_comment' => "TEXT DEFAULT NULL",
        'review_stars'  => "INT DEFAULT NULL",
        'review_comment'=> "TEXT DEFAULT NULL",
        'internal_notes'=> "JSON DEFAULT NULL",  // or TEXT if MariaDB version is old
        'system_logs'   => "JSON DEFAULT NULL",   // or TEXT if MariaDB version is old
        'slug'          => "VARCHAR(32) UNIQUE DEFAULT NULL",
        'vat_enabled'   => "TINYINT(1) DEFAULT 0",
        'vat_amount'    => "DECIMAL(10,2) DEFAULT 0.00",
        'repair_stage'  => "VARCHAR(50) NULL DEFAULT NULL",
        'repair_assignments' => "JSON NULL DEFAULT NULL",
        'stage_timers'  => "JSON NULL DEFAULT NULL",
        'stage_statuses' => "JSON NULL DEFAULT NULL",
        // Payment tracking
        'amount_paid'   => "DECIMAL(10,2) DEFAULT 0.00",
        'payment_status' => "ENUM('unpaid','partial','paid') DEFAULT 'unpaid'",
        'last_payment_at' => "DATETIME DEFAULT NULL"
    ];

    foreach ($columns as $col => $def) {
        if (!columnExists($pdo, 'transfers', $col)) {
            $pdo->exec("ALTER TABLE transfers ADD COLUMN $col $def");
            echo " - Added missing column: $col\n";
        } else {
            echo " - Column $col exists.\n";
        }
    }

    // ---------------------------------------------------------
    // 2. TABLE: sms_templates
    // ---------------------------------------------------------
    echo "\nChecking table 'sms_templates'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS sms_templates (
        slug VARCHAR(50) PRIMARY KEY,
        content TEXT
    )";
    $pdo->exec($sql);
    echo " - Table structure verified.\n";

    // ---------------------------------------------------------
    // 3. TABLE: vehicles
    // ---------------------------------------------------------
    echo "\nChecking table 'vehicles'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS vehicles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plate VARCHAR(20) UNIQUE NOT NULL,
        ownerName VARCHAR(100),
        phone VARCHAR(20),
        model VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo " - Table structure verified.\n";

    // ---------------------------------------------------------
    // 4. TABLE: manager_tokens (For Firebase)
    // ---------------------------------------------------------
    echo "\nChecking table 'manager_tokens'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS manager_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        token TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo " - Table structure verified.\n";

    // ---------------------------------------------------------
    // 4.5. TABLE: users (User Management System)
    // ---------------------------------------------------------
    echo "\nChecking table 'users'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        role ENUM('admin', 'manager', 'viewer', 'technician', 'operator') DEFAULT 'manager',
        status ENUM('active', 'inactive') DEFAULT 'active',
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INT DEFAULT NULL,
        INDEX idx_username (username),
        INDEX idx_role (role),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    
    // Ensure role column includes 'operator'
    try {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'manager', 'viewer', 'technician', 'operator') DEFAULT 'manager'");
        echo " - Role column updated to include 'operator'.\n";
    } catch (Exception $e) {
        echo " - Role column update skipped (may already have correct values).\n";
    }
    echo " - Table structure verified.\n";

    // Create default admin user if no users exist
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    if ($result['count'] == 0) {
        $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password, full_name, role, status) VALUES (?, ?, ?, 'admin', 'active')")
            ->execute(['admin', $defaultPassword, 'System Administrator']);
        echo " - Default admin user created (username: admin, password: admin123)\n";
    }

    // ---------------------------------------------------------
    // 4.6. TABLE: translations (Multilanguage System)
    // ---------------------------------------------------------
    echo "\nChecking table 'translations'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS translations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        translation_key VARCHAR(255) NOT NULL,
        language_code VARCHAR(5) NOT NULL,
        translation_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_key_lang (translation_key, language_code),
        INDEX idx_language (language_code),
        INDEX idx_key (translation_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo " - Table structure verified.\n";

    // ---------------------------------------------------------
    // 5. TABLE: customer_reviews
    // ---------------------------------------------------------
    echo "\nChecking table 'customer_reviews'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS customer_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(100) NOT NULL,
        customer_name VARCHAR(255),
        customer_email VARCHAR(255),
        rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        ip_address VARCHAR(45),
        INDEX idx_order_id (order_id),
        INDEX idx_created_at (created_at),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo " - Table structure verified.\n";

    // ---------------------------------------------------------
    // 6. TABLE: sms_parsing_templates (SMS Parsing System)
    // ---------------------------------------------------------
    echo "\nChecking table 'sms_parsing_templates'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS sms_parsing_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        insurance_company VARCHAR(100) NOT NULL,
        template_pattern TEXT NOT NULL,
        field_mappings JSON NOT NULL,
        is_active BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_insurance_company (insurance_company),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo " - Table structure verified.\n";

    // Insert default SMS parsing templates if table is empty
    $countStmt = $pdo->query("SELECT COUNT(*) FROM sms_parsing_templates");
    $templateCount = $countStmt->fetchColumn();
    if ($templateCount == 0) {
        $templates = [
            ['Transfer Format', 'Generic Transfer', 'Transfer from [NAME], Plate: [PLATE], Amt: [AMOUNT]', '[{"field": "name", "pattern": "Transfer from", "description": "Customer name after \\"Transfer from\\""}, {"field": "plate", "pattern": "Plate:", "description": "Plate number after \\"Plate:\\""}, {"field": "amount", "pattern": "Amt:", "description": "Amount after \\"Amt:\\""}]'],
            ['Insurance Pay Format', 'Generic Insurance', 'INSURANCE PAY | [PLATE] | [NAME] | [AMOUNT]', '[{"field": "plate", "pattern": "INSURANCE PAY |", "description": "Plate number after INSURANCE PAY |"}, {"field": "name", "pattern": "|", "description": "Customer name between pipes"}, {"field": "amount", "pattern": "|", "description": "Amount after last pipe"}]'],
            ['User Format', 'Generic User', 'User: [NAME] Car: [PLATE] Sum: [AMOUNT]', '[{"field": "name", "pattern": "User:", "description": "Customer name after \\"User:\\""}, {"field": "plate", "pattern": "Car:", "description": "Plate number after \\"Car:\\""}, {"field": "amount", "pattern": "Sum:", "description": "Amount after \\"Sum:\\""}]'],
            ['Aldagi Standard', 'Aldagi Insurance', 'მანქანის ნომერი: [PLATE] დამზღვევი: [NAME], [AMOUNT] (ფრანშიზა [FRANCHISE])', '[{"field": "plate", "pattern": "მანქანის ნომერი:", "description": "Plate number after Georgian text"}, {"field": "name", "pattern": "დამზღვევი:", "description": "Customer name after Georgian text"}, {"field": "amount", "pattern": ",", "description": "Amount after comma"}, {"field": "franchise", "pattern": "(ფრანშიზა", "description": "Franchise amount in parentheses after Georgian text"}]'],
            ['Ardi Standard', 'Ardi Insurance', 'სახ. ნომ [PLATE] [AMOUNT] (ფრანშიზა [FRANCHISE])', '[{"field": "plate", "pattern": "სახ. ნომ", "description": "Plate number after Georgian abbreviation"}, {"field": "amount", "pattern": "", "description": "Amount at the end"}, {"field": "franchise", "pattern": "(ფრანშიზა", "description": "Franchise amount in parentheses after Georgian text"}]'],
            ['Imedi L Standard', 'Imedi L Insurance', '[MAKE] ([PLATE]) [AMOUNT] (ფრანშიზა [FRANCHISE])', '[{"field": "plate", "pattern": "(", "description": "Plate number in parentheses"}, {"field": "amount", "pattern": ")", "description": "Amount after closing parenthesis"}, {"field": "franchise", "pattern": "(ფრანშიზა", "description": "Franchise amount in parentheses after Georgian text"}]'],
            ['Franchise Parser', 'Generic Franchise', '[TEXT] (ფრანშიზა [FRANCHISE])', '[{"field": "franchise", "pattern": "(ფრანშიზა", "description": "Franchise amount in parentheses after Georgian text"}]']
        ];

        $insertStmt = $pdo->prepare("INSERT INTO sms_parsing_templates (name, insurance_company, template_pattern, field_mappings) VALUES (?, ?, ?, ?)");
        foreach ($templates as $template) {
            $insertStmt->execute($template);
        }
        echo " - Default SMS parsing templates inserted.\n";
    } else {
        echo " - SMS parsing templates already exist.\n";
    }

    // ---------------------------------------------------------
    // 7. TABLE: parts_collections (Car Parts Collection System)
    // ---------------------------------------------------------
    echo "\nChecking table 'parts_collections'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS parts_collections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transfer_id INT NOT NULL,
        assigned_manager_id INT DEFAULT NULL COMMENT 'ID of assigned manager from users table',
        parts_list JSON NOT NULL COMMENT 'Array of parts: [{name, quantity, price}]',
        status VARCHAR(50) DEFAULT 'pending' COMMENT 'pending, collected, cancelled, etc.',
        total_cost DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Calculated total from parts_list',
        currency VARCHAR(3) DEFAULT 'GEL' COMMENT 'Currency for the collection: GEL',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (transfer_id) REFERENCES transfers(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_manager_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_transfer_id (transfer_id),
        INDEX idx_assigned_manager (assigned_manager_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo " - Table structure verified.\n";

    // List of required columns for 'parts_collections'
    $columns = [
        'assigned_manager_id' => "INT DEFAULT NULL COMMENT 'ID of assigned manager from users table'",
        'currency' => "VARCHAR(3) DEFAULT 'GEL' COMMENT 'Currency for the collection: GEL'",
        'description' => "TEXT DEFAULT NULL COMMENT 'Description of the parts collection request'",
        'collection_type' => "VARCHAR(16) DEFAULT 'local' COMMENT 'Collection type: local or order'",
    ];

    foreach ($columns as $col => $def) {
        if (!columnExists($pdo, 'parts_collections', $col)) {
            $pdo->exec("ALTER TABLE parts_collections ADD COLUMN $col $def");
            echo " - Added missing column: $col\n";
        } else {
            echo " - Column $col exists.\n";
        }
    }

    // Add foreign key if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE parts_collections ADD CONSTRAINT fk_assigned_manager FOREIGN KEY (assigned_manager_id) REFERENCES users(id) ON DELETE SET NULL");
        echo " - Added foreign key constraint for assigned_manager_id\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            echo " - Foreign key constraint already exists or error: " . $e->getMessage() . "\n";
        } else {
            echo " - Foreign key constraint already exists.\n";
        }
    }

    // Add index if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE parts_collections ADD INDEX idx_assigned_manager (assigned_manager_id)");
        echo " - Added index for assigned_manager_id\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            echo " - Index already exists or error: " . $e->getMessage() . "\n";
        } else {
            echo " - Index already exists.\n";
        }
    }

    // ---------------------------------------------------------
    // 8. TABLE: payments (Payments/Income for transfers)
    // ---------------------------------------------------------
    echo "\nChecking table 'payments'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transfer_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        method ENUM('cash','transfer') NOT NULL DEFAULT 'cash',
        reference VARCHAR(255) DEFAULT NULL,
        recorded_by INT DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        currency VARCHAR(3) DEFAULT 'GEL',
        paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (transfer_id) REFERENCES transfers(id) ON DELETE CASCADE,
        FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_transfer_id (transfer_id),
        INDEX idx_method (method)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo " - Table structure verified.\n";

    // Ensure required payments columns exist (for older installs)
    $payments_required = [
        'method' => "ENUM('cash','transfer') NOT NULL DEFAULT 'cash'",
        'reference' => "VARCHAR(255) DEFAULT NULL",
        'recorded_by' => "INT DEFAULT NULL",
        'notes' => "TEXT DEFAULT NULL",
        'currency' => "VARCHAR(3) DEFAULT 'GEL'",
        'paid_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        'payment_date' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"  // For legacy schemas
    ];
    foreach ($payments_required as $col => $def) {
        if (!columnExists($pdo, 'payments', $col)) {
            $pdo->exec("ALTER TABLE payments ADD COLUMN $col $def");
            echo " - Added missing column to payments: $col\n";
        } else {
            echo " - Column payments.$col exists.\n";
        }
    }

    echo "---------------------------------\n";

    // Fix SMS Templates table
    require_once 'fix_sms_templates.php';

    // Fix or create item suggestions table
    require_once 'fix_db_suggestions.php';

    echo "---------------------------------\n";
    echo "All database checks and fixes are complete.\n";
    echo "---------------------------------\n";

} catch (PDOException $e) {
    echo "CRITICAL ERROR: " . $e->getMessage();
}

// Helper function
function columnExists($pdo, $table, $column) {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM $table LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetch();
}
?>