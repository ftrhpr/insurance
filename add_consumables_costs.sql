-- Create consumables_costs table for tracking monthly consumables costs per technician
CREATE TABLE IF NOT EXISTS `consumables_costs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `technician_name` VARCHAR(255) NOT NULL,
    `year_month` VARCHAR(7) NOT NULL,
    `cost` DECIMAL(10, 2) NOT NULL DEFAULT 0,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_tech_month` (`technician_name`, `year_month`)
);
