-- Add columns for the new repair workflow feature
ALTER TABLE `transfers`
ADD COLUMN `repair_stage` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Current stage in the 6-step repair workflow',
ADD COLUMN `repair_assignments` JSON NULL DEFAULT NULL COMMENT 'JSON object mapping stages to assigned technicians';

-- For existing cases that are in progress, set a default starting stage.
UPDATE `transfers`
SET `repair_stage` = 'disassembly'
WHERE `status` IN ('Processing', 'Called', 'Parts Ordered', 'Parts Arrived', 'Scheduled') AND `repair_stage` IS NULL;
