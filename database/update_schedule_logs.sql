-- =====================================================
-- Update Schedule Logs Table Structure
-- Version: 3.2.0
-- Created: December 2024
-- =====================================================

USE `water_quality_db`;

-- Check if scheduled_time column exists
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'water_quality_db'
    AND TABLE_NAME = 'schedule_logs'
    AND COLUMN_NAME = 'scheduled_time'
);

-- Add scheduled_time column if it doesn't exist
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE schedule_logs ADD COLUMN scheduled_time DATETIME NULL AFTER action',
    'SELECT "scheduled_time column already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if executed_time column exists
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'water_quality_db'
    AND TABLE_NAME = 'schedule_logs'
    AND COLUMN_NAME = 'executed_time'
);

-- Add executed_time column if it doesn't exist
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE schedule_logs ADD COLUMN executed_time DATETIME NULL AFTER scheduled_time',
    'SELECT "executed_time column already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if details column exists (rename error_message to details)
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'water_quality_db'
    AND TABLE_NAME = 'schedule_logs'
    AND COLUMN_NAME = 'details'
);

-- Add details column if it doesn't exist
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE schedule_logs ADD COLUMN details TEXT NULL AFTER success',
    'SELECT "details column already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing records to populate scheduled_time and executed_time
-- This will populate the new fields based on the schedule information
UPDATE schedule_logs sl
JOIN relay_schedules rs ON sl.schedule_id = rs.id
SET sl.scheduled_time = CONCAT(rs.schedule_date, ' ', rs.schedule_time),
    sl.executed_time = sl.execution_time,
    sl.details = sl.error_message
WHERE sl.scheduled_time IS NULL;

-- Add indexes for better performance
ALTER TABLE schedule_logs 
ADD INDEX idx_scheduled_time (scheduled_time),
ADD INDEX idx_executed_time (executed_time);

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