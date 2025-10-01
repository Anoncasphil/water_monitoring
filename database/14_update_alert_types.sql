-- Update alert_acknowledgments table to support pH alerts
-- This migration adds 'ph' to the alert_type ENUM

-- First, check if the table exists
SELECT COUNT(*) FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name = 'alert_acknowledgments';

-- Update the ENUM to include 'ph' if table exists
-- Note: This will work if there are no existing records with 'ph' type
-- If there are existing records, you may need to handle this differently

-- For MySQL 8.0+, we can use ALTER TABLE to modify the ENUM
ALTER TABLE alert_acknowledgments 
MODIFY COLUMN alert_type ENUM('turbidity', 'tds', 'ph') NOT NULL 
COMMENT 'Type of alert that was acknowledged';

-- Update the constraint as well
ALTER TABLE alert_acknowledgments 
DROP CHECK IF EXISTS chk_action_taken;

ALTER TABLE alert_acknowledgments 
ADD CONSTRAINT chk_action_taken CHECK (action_taken IN (
    'filter_replacement', 'system_maintenance', 'chemical_treatment',
    'system_flush', 'investigation', 'manual_intervention', 'other'
));
