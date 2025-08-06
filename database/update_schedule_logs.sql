-- =====================================================
-- Update Schedule Logs Table Structure
-- Version: 3.2.0
-- Created: December 2024
-- =====================================================

USE `water_quality_db`;

-- Add scheduled_time column if it doesn't exist
ALTER TABLE schedule_logs 
ADD COLUMN IF NOT EXISTS scheduled_time DATETIME NULL AFTER action;

-- Add executed_time column if it doesn't exist
ALTER TABLE schedule_logs 
ADD COLUMN IF NOT EXISTS executed_time DATETIME NULL AFTER scheduled_time;

-- Add details column if it doesn't exist
ALTER TABLE schedule_logs 
ADD COLUMN IF NOT EXISTS details TEXT NULL AFTER success;

-- Update existing records to populate scheduled_time and executed_time
-- This will populate the new fields based on the schedule information
UPDATE schedule_logs sl
JOIN relay_schedules rs ON sl.schedule_id = rs.id
SET sl.scheduled_time = CONCAT(rs.schedule_date, ' ', rs.schedule_time),
    sl.executed_time = sl.execution_time,
    sl.details = sl.error_message
WHERE sl.scheduled_time IS NULL;

-- Add indexes for better performance (ignore if they already exist)
ALTER TABLE schedule_logs 
ADD INDEX IF NOT EXISTS idx_scheduled_time (scheduled_time),
ADD INDEX IF NOT EXISTS idx_executed_time (executed_time);

-- Show the updated table structure
DESCRIBE schedule_logs;

-- Show sample of updated data
SELECT 
    sl.id,
    sl.schedule_id,
    sl.relay_number,
    sl.action,
    sl.scheduled_time,
    sl.executed_time,
    sl.success,
    sl.details
FROM schedule_logs sl
ORDER BY sl.executed_time DESC
LIMIT 5; 