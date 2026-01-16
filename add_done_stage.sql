-- Add "Done" stage to the repair workflow, ensuring it is the last stage.
INSERT INTO `repair_stages` (`id`, `stage_name`, `stage_order`) VALUES ('done', 'Done', 9) ON DUPLICATE KEY UPDATE stage_name = 'Done', stage_order = 9;
