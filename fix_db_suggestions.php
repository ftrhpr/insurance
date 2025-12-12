<?php
// This script should be included by fix_db_all.php which handles the PDO connection.
if (!isset($pdo)) {
    require_once 'config.php';
    try {
        $pdo = getDBConnection();
    } catch (Exception $e) {
        die("DB connection failed: " . $e->getMessage() . "\n");
    }
}

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS `item_suggestions` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
      `type` ENUM('part', 'labor') NOT NULL,
      `usage_count` INT(11) NOT NULL DEFAULT 1,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `name_type` (`name`, `type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($sql);
    echo "Table 'item_suggestions' is ready.\n";
} catch (Exception $e) {
    echo "Error creating 'item_suggestions' table: " . $e->getMessage() . "\n";
}
