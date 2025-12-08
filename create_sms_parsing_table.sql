-- SQL script to create the sms_parsing_templates table
-- Run this in your MySQL database management tool or command line

USE otoexpre_userdb;

CREATE TABLE IF NOT EXISTS sms_parsing_templates (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample templates
INSERT INTO sms_parsing_templates (name, insurance_company, template_pattern, field_mappings) VALUES
('Transfer Format', 'Generic Transfer', 'Transfer from [NAME], Plate: [PLATE], Amt: [AMOUNT]', '[{"field": "name", "pattern": "Transfer from", "description": "Customer name after \\"Transfer from\\""}, {"field": "plate", "pattern": "Plate:", "description": "Plate number after \\"Plate:\\""}, {"field": "amount", "pattern": "Amt:", "description": "Amount after \\"Amt:\\""}]'),
('Insurance Pay Format', 'Generic Insurance', 'INSURANCE PAY | [PLATE] | [NAME] | [AMOUNT]', '[{"field": "plate", "pattern": "INSURANCE PAY |", "description": "Plate number after INSURANCE PAY |"}, {"field": "name", "pattern": "|", "description": "Customer name between pipes"}, {"field": "amount", "pattern": "|", "description": "Amount after last pipe"}]'),
('User Format', 'Generic User', 'User: [NAME] Car: [PLATE] Sum: [AMOUNT]', '[{"field": "name", "pattern": "User:", "description": "Customer name after \\"User:\\""}, {"field": "plate", "pattern": "Car:", "description": "Plate number after \\"Car:\\""}, {"field": "amount", "pattern": "Sum:", "description": "Amount after \\"Sum:\\""}]'),
('Aldagi Standard', 'Aldagi Insurance', 'მანქანის ნომერი: [PLATE] დამზღვევი: [NAME], [AMOUNT]', '[{"field": "plate", "pattern": "მანქანის ნომერი:", "description": "Plate number after Georgian text"}, {"field": "name", "pattern": "დამზღვევი:", "description": "Customer name after Georgian text"}, {"field": "amount", "pattern": ",", "description": "Amount after comma"}]'),
('Ardi Standard', 'Ardi Insurance', 'სახ. ნომ [PLATE] [AMOUNT]', '[{"field": "plate", "pattern": "სახ. ნომ", "description": "Plate number after Georgian abbreviation"}, {"field": "amount", "pattern": "", "description": "Amount at the end"}]'),
('Imedi L Standard', 'Imedi L Insurance', '[MAKE] ([PLATE]) [AMOUNT]', '[{"field": "plate", "pattern": "(", "description": "Plate number in parentheses"}, {"field": "amount", "pattern": ")", "description": "Amount after closing parenthesis"}]')
ON DUPLICATE KEY UPDATE name = VALUES(name);

SELECT 'sms_parsing_templates table created successfully!' as status;