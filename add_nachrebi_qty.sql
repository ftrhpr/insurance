-- Add nachrebi_qty column to transfers table
ALTER TABLE transfers 
ADD COLUMN nachrebi_qty INT DEFAULT 0 COMMENT 'Pieces quantity (ნაჭრების რაოდენობა)';
