-- =====================================================
-- Add Manual Override Column to Relay States Table
-- Version: 3.2.0
-- Created: December 2024
-- Purpose: Prevent automation from overriding manual pump control
-- =====================================================

USE `water_quality_db`;

-- Add manual_override column to relay_states table
ALTER TABLE `relay_states` 
ADD COLUMN `manual_override` tinyint(1) NOT NULL DEFAULT 0 
COMMENT 'Manual override flag: 1 = manually controlled, 0 = automation controlled' 
AFTER `state`;

-- Update existing relays to have no manual override (automation controlled)
UPDATE `relay_states` SET `manual_override` = 0 WHERE `manual_override` IS NULL;

-- Add index for manual override column
ALTER TABLE `relay_states` 
ADD INDEX `idx_manual_override` (`manual_override`);

-- Verify the changes
DESCRIBE `relay_states`;

-- Show current relay states with manual override
SELECT 
    relay_number,
    CASE 
        WHEN relay_number = 1 THEN 'Filter Pump'
        WHEN relay_number = 2 THEN 'Dispense Water'
        WHEN relay_number = 3 THEN 'Reserved 3'
        WHEN relay_number = 4 THEN 'Reserved 4'
        ELSE 'Unknown'
    END as relay_name,
    CASE 
        WHEN state = 1 THEN 'ON'
        WHEN state = 0 THEN 'OFF'
        ELSE 'Unknown'
    END as current_state,
    CASE 
        WHEN manual_override = 1 THEN 'Manual Control'
        WHEN manual_override = 0 THEN 'Automation Control'
        ELSE 'Unknown'
    END as control_mode,
    last_updated
FROM `relay_states` 
ORDER BY relay_number;
