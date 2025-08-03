-- =====================================================
-- Indexes and Constraints for Water Quality Monitoring System
-- Version: 3.1.0
-- Created: December 2024
-- =====================================================

USE `water_quality_db`;

-- =====================================================
-- FOREIGN KEY CONSTRAINTS
-- =====================================================

-- Add foreign key constraint for activity_logs.user_id
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` 
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) 
  ON DELETE SET NULL;

-- Add foreign key constraint for schedule_logs.schedule_id
ALTER TABLE `schedule_logs`
  ADD CONSTRAINT `schedule_logs_ibfk_1` 
  FOREIGN KEY (`schedule_id`) REFERENCES `relay_schedules` (`id`) 
  ON DELETE CASCADE;

-- =====================================================
-- ADDITIONAL INDEXES FOR PERFORMANCE
-- =====================================================

-- Composite indexes for better query performance
ALTER TABLE `water_readings` 
  ADD INDEX `idx_reading_time_turbidity` (`reading_time`, `turbidity`),
  ADD INDEX `idx_reading_time_tds` (`reading_time`, `tds`),
  ADD INDEX `idx_reading_time_ph` (`reading_time`, `ph`),
  ADD INDEX `idx_reading_time_temperature` (`reading_time`, `temperature`);

-- Index for date range queries on water_readings
ALTER TABLE `water_readings` 
  ADD INDEX `idx_date_range` (`reading_time`);

-- Index for schedule queries
ALTER TABLE `schedules` 
  ADD INDEX `idx_active_schedules` (`is_active`, `schedule_type`, `time`),
  ADD INDEX `idx_recurring_schedules` (`schedule_type`, `days`, `time`);

-- Index for relay state queries
ALTER TABLE `relay_states` 
  ADD INDEX `idx_relay_state` (`relay_number`, `state`);

-- Index for activity log queries
ALTER TABLE `activity_logs` 
  ADD INDEX `idx_user_activity` (`user_id`, `action_type`, `timestamp`),
  ADD INDEX `idx_activity_date_range` (`timestamp`);

-- =====================================================
-- FULLTEXT INDEXES FOR SEARCH
-- =====================================================

-- Fulltext index for schedule descriptions
ALTER TABLE `schedules` 
  ADD FULLTEXT INDEX `ft_schedule_description` (`name`, `description`);

-- Fulltext index for activity log messages
ALTER TABLE `activity_logs` 
  ADD FULLTEXT INDEX `ft_activity_message` (`message`, `details`);

-- =====================================================
-- PARTITIONING (Optional - for large datasets)
-- =====================================================

-- Note: Uncomment the following lines if you have large datasets
-- and want to partition the water_readings table by month

/*
ALTER TABLE `water_readings` 
PARTITION BY RANGE (YEAR(reading_time) * 100 + MONTH(reading_time)) (
    PARTITION p202501 VALUES LESS THAN (202502),
    PARTITION p202502 VALUES LESS THAN (202503),
    PARTITION p202503 VALUES LESS THAN (202504),
    PARTITION p202504 VALUES LESS THAN (202505),
    PARTITION p202505 VALUES LESS THAN (202506),
    PARTITION p202506 VALUES LESS THAN (202507),
    PARTITION p202507 VALUES LESS THAN (202508),
    PARTITION p202508 VALUES LESS THAN (202509),
    PARTITION p202509 VALUES LESS THAN (202510),
    PARTITION p202510 VALUES LESS THAN (202511),
    PARTITION p202511 VALUES LESS THAN (202512),
    PARTITION p202512 VALUES LESS THAN (202601),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
*/

-- =====================================================
-- VIEWS FOR COMMON QUERIES
-- =====================================================

-- View for latest water quality readings
CREATE OR REPLACE VIEW `latest_readings` AS
SELECT 
    `turbidity`,
    `tds`,
    `ph`,
    `temperature`,
    `reading_time`
FROM `water_readings` 
ORDER BY `reading_time` DESC 
LIMIT 1;

-- View for active schedules
CREATE OR REPLACE VIEW `active_schedules` AS
SELECT 
    `id`,
    `name`,
    `relay_number`,
    `action`,
    `schedule_type`,
    `time`,
    `date`,
    `days`,
    `last_executed`
FROM `schedules` 
WHERE `is_active` = 1;

-- View for relay status summary
CREATE OR REPLACE VIEW `relay_status_summary` AS
SELECT 
    `relay_number`,
    `state`,
    CASE 
        WHEN `relay_number` = 1 THEN 'Filter'
        WHEN `relay_number` = 2 THEN 'Dispense Water'
        WHEN `relay_number` = 3 THEN 'Relay 3'
        WHEN `relay_number` = 4 THEN 'Relay 4'
        ELSE CONCAT('Relay ', `relay_number`)
    END AS `relay_name`,
    `last_updated`
FROM `relay_states` 
ORDER BY `relay_number`;

-- =====================================================
-- STORED PROCEDURES
-- =====================================================

-- Procedure to get water quality statistics
DELIMITER //
CREATE PROCEDURE `GetWaterQualityStats`(IN days_back INT)
BEGIN
    SELECT 
        COUNT(*) as total_readings,
        AVG(turbidity) as avg_turbidity,
        AVG(tds) as avg_tds,
        AVG(ph) as avg_ph,
        AVG(temperature) as avg_temperature,
        MIN(turbidity) as min_turbidity,
        MAX(turbidity) as max_turbidity,
        MIN(tds) as min_tds,
        MAX(tds) as max_tds,
        MIN(ph) as min_ph,
        MAX(ph) as max_ph,
        MIN(temperature) as min_temp,
        MAX(temperature) as max_temp
    FROM water_readings 
    WHERE reading_time >= DATE_SUB(NOW(), INTERVAL days_back DAY);
END //
DELIMITER ;

-- Procedure to clean old data
DELIMITER //
CREATE PROCEDURE `CleanOldData`(IN days_to_keep INT)
BEGIN
    DELETE FROM water_readings 
    WHERE reading_time < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    DELETE FROM activity_logs 
    WHERE timestamp < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    DELETE FROM schedule_logs 
    WHERE execution_time < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
END //
DELIMITER ;

-- =====================================================
-- TRIGGERS
-- =====================================================

-- Trigger to update relay_states when schedules are executed
DELIMITER //
CREATE TRIGGER `update_relay_state_after_schedule` 
AFTER INSERT ON `schedule_logs`
FOR EACH ROW
BEGIN
    UPDATE `relay_states` 
    SET `state` = NEW.action, `last_updated` = NEW.execution_time
    WHERE `relay_number` = NEW.relay_number;
END //
DELIMITER ;

-- =====================================================
-- FINAL COMMIT
-- =====================================================

COMMIT; 