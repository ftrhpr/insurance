-- Add nachrebi_qty column to transfers table
ALTER TABLE transfers 
ADD COLUMN nachrebi_qty DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Pieces quantity (ნაჭრების რაოდენობა)';
