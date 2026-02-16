-- Add completion signature columns to transfers table
-- Run this migration to enable digital signature on case completion
-- Run each statement separately in phpMyAdmin

ALTER TABLE transfers ADD COLUMN completion_signature MEDIUMTEXT DEFAULT NULL;
ALTER TABLE transfers ADD COLUMN signature_date DATETIME DEFAULT NULL;
