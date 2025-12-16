-- Create table for SMS recipients used by admin/manager
CREATE TABLE IF NOT EXISTS sms_recipients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(64) NOT NULL,
  type VARCHAR(50) DEFAULT 'system',
  description TEXT NULL,
  workflow_stages JSON DEFAULT NULL,
  template_slug VARCHAR(100) DEFAULT NULL,
  enabled TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
