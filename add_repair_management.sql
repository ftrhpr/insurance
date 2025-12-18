-- Add repair management fields to transfers table
ALTER TABLE transfers
ADD COLUMN repair_status VARCHAR(50) DEFAULT NULL,
ADD COLUMN repair_start_date DATETIME DEFAULT NULL,
ADD COLUMN repair_end_date DATETIME DEFAULT NULL,
ADD COLUMN assigned_mechanic VARCHAR(100) DEFAULT NULL,
ADD COLUMN repair_notes TEXT DEFAULT NULL,
ADD COLUMN repair_parts TEXT DEFAULT NULL,
ADD COLUMN repair_labor TEXT DEFAULT NULL,
ADD COLUMN repair_activity_log TEXT DEFAULT NULL;