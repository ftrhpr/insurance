-- Add case_type column to transfers table
-- Values: 'საცალო' (retail) or 'დაზღვევა' (insurance)

ALTER TABLE transfers ADD COLUMN case_type ENUM('საცალო', 'დაზღვევა') DEFAULT 'საცალო' AFTER amount;

-- Update existing records to have a default value
UPDATE transfers SET case_type = 'საცალო' WHERE case_type IS NULL;

-- Update all cases to insurance type
UPDATE transfers SET case_type = 'დაზღვევა';