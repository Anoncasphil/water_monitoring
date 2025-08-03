-- =====================================================
-- Sample Data for Water Quality Monitoring System
-- Version: 3.1.0
-- Created: December 2024
-- =====================================================

USE `water_quality_db`;

-- =====================================================
-- ADDITIONAL SAMPLE WATER READINGS
-- =====================================================

-- Insert more realistic water quality readings for testing
INSERT INTO `water_readings` (`turbidity`, `tds`, `ph`, `temperature`, `in`, `reading_time`) VALUES
-- Recent readings (last 24 hours)
(1.8, 145, 7.1, 24.8, 0, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(1.6, 142, 7.2, 25.1, 0, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(1.9, 148, 7.0, 25.3, 0, DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(1.7, 146, 7.3, 25.0, 0, DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(1.5, 140, 7.1, 24.9, 0, DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(2.1, 152, 7.4, 25.5, 0, DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(1.4, 138, 7.0, 24.7, 0, DATE_SUB(NOW(), INTERVAL 7 HOUR)),
(1.8, 150, 7.2, 25.2, 0, DATE_SUB(NOW(), INTERVAL 8 HOUR)),

-- Historical readings (last week)
(1.6, 145, 7.1, 25.0, 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1.9, 148, 7.3, 25.4, 0, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1.7, 146, 7.2, 25.1, 0, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2.0, 150, 7.4, 25.6, 0, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1.5, 142, 7.0, 24.8, 0, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1.8, 147, 7.2, 25.3, 0, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1.6, 144, 7.1, 25.0, 0, DATE_SUB(NOW(), INTERVAL 7 DAY)),

-- Historical readings (last month)
(1.7, 146, 7.2, 25.1, 0, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(1.9, 149, 7.3, 25.5, 0, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(1.6, 143, 7.1, 24.9, 0, DATE_SUB(NOW(), INTERVAL 25 DAY)),
(2.1, 151, 7.4, 25.7, 0, DATE_SUB(NOW(), INTERVAL 30 DAY));

-- =====================================================
-- ADDITIONAL SAMPLE SCHEDULES
-- =====================================================

-- Insert more sample schedules
INSERT INTO `schedules` (`name`, `relay_number`, `action`, `schedule_type`, `time`, `date`, `days`, `is_active`, `created_at`) VALUES
('Weekend Filter Start', 1, 'on', 'recurring', '09:00:00', NULL, '6,7', 1, NOW()),
('Weekend Filter Stop', 1, 'off', 'recurring', '17:00:00', NULL, '6,7', 1, NOW()),
('Monthly Maintenance', 2, 'on', 'recurring', '10:00:00', NULL, '1', 1, NOW()),
('Monthly Maintenance Stop', 2, 'off', 'recurring', '11:00:00', NULL, '1', 1, NOW()),
('Emergency Test', 1, 'on', 'one-time', '14:30:00', CURDATE(), NULL, 1, NOW());

-- =====================================================
-- ADDITIONAL SAMPLE ACTIVITY LOGS
-- =====================================================

-- Insert more sample activity logs
INSERT INTO `activity_logs` (`user_id`, `action_type`, `performed_by`, `message`, `details`, `timestamp`) VALUES
(1, 'schedule_created', 'System Administrator', 'Created new schedule', 'Schedule: Weekend Filter Start for Relay 1', NOW()),
(1, 'schedule_updated', 'System Administrator', 'Updated schedule', 'Schedule: Morning Filter Start - Changed time to 08:00', NOW()),
(1, 'relay_controlled', 'System Administrator', 'Manual relay control', 'Relay 1 turned ON manually', NOW()),
(1, 'data_exported', 'System Administrator', 'Exported water quality data', 'CSV export of last 30 days data', NOW()),
(1, 'system_backup', 'System Administrator', 'System backup created', 'Full database backup completed', NOW()),
(2, 'user_login', 'John Doe', 'User logged in', 'Login from IP: 192.168.1.100', NOW()),
(2, 'data_viewed', 'John Doe', 'Viewed analytics dashboard', 'Accessed water quality analytics', NOW()),
(3, 'user_login', 'Jane Smith', 'User logged in', 'Login from IP: 192.168.1.101', NOW()),
(3, 'schedule_deleted', 'Jane Smith', 'Deleted schedule', 'Schedule: Test Schedule removed', NOW());

-- =====================================================
-- ADDITIONAL SAMPLE SCHEDULE LOGS
-- =====================================================

-- Insert more sample schedule execution logs
INSERT INTO `schedule_logs` (`schedule_id`, `relay_number`, `action`, `execution_time`, `success`, `error_message`) VALUES
(1, 1, 1, DATE_SUB(NOW(), INTERVAL 1 DAY), 1, NULL),
(2, 1, 0, DATE_SUB(NOW(), INTERVAL 1 DAY), 1, NULL),
(3, 2, 1, DATE_SUB(NOW(), INTERVAL 7 DAY), 1, NULL),
(4, 2, 0, DATE_SUB(NOW(), INTERVAL 7 DAY), 1, NULL),
(5, 1, 1, DATE_SUB(NOW(), INTERVAL 2 DAY), 1, NULL),
(1, 1, 1, DATE_SUB(NOW(), INTERVAL 2 DAY), 1, NULL),
(2, 1, 0, DATE_SUB(NOW(), INTERVAL 2 DAY), 1, NULL),
(1, 1, 1, DATE_SUB(NOW(), INTERVAL 3 DAY), 1, NULL),
(2, 1, 0, DATE_SUB(NOW(), INTERVAL 3 DAY), 1, NULL),
(1, 1, 1, DATE_SUB(NOW(), INTERVAL 4 DAY), 1, NULL),
(2, 1, 0, DATE_SUB(NOW(), INTERVAL 4 DAY), 1, NULL);

-- =====================================================
-- UPDATE RELAY STATES WITH SAMPLE DATA
-- =====================================================

-- Update relay states to reflect some activity
UPDATE `relay_states` SET `state` = 1, `last_updated` = NOW() WHERE `relay_number` = 1;
UPDATE `relay_states` SET `state` = 0, `last_updated` = NOW() WHERE `relay_number` = 2;

-- =====================================================
-- SAMPLE ALERTS DATA (if alerts table exists)
-- =====================================================

-- Note: This section can be uncommented if an alerts table is added later
/*
CREATE TABLE IF NOT EXISTS `alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alert_type` enum('warning','critical','info') NOT NULL,
  `parameter` varchar(50) NOT NULL,
  `value` float NOT NULL,
  `threshold` float NOT NULL,
  `message` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_alert_type` (`alert_type`),
  KEY `idx_parameter` (`parameter`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `alerts` (`alert_type`, `parameter`, `value`, `threshold`, `message`, `created_at`) VALUES
('warning', 'turbidity', 2.1, 2.0, 'Turbidity level is above normal range', NOW()),
('critical', 'ph', 8.5, 8.0, 'pH level is critically high', NOW()),
('info', 'temperature', 25.7, 25.0, 'Temperature is slightly elevated', NOW());
*/

-- =====================================================
-- FINAL COMMIT
-- =====================================================

COMMIT;

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================

-- Verify data was inserted correctly
SELECT 'Water Readings Count:' as info, COUNT(*) as count FROM water_readings
UNION ALL
SELECT 'Users Count:', COUNT(*) FROM users
UNION ALL
SELECT 'Schedules Count:', COUNT(*) FROM schedules
UNION ALL
SELECT 'Activity Logs Count:', COUNT(*) FROM activity_logs
UNION ALL
SELECT 'Schedule Logs Count:', COUNT(*) FROM schedule_logs
UNION ALL
SELECT 'Relay States Count:', COUNT(*) FROM relay_states;

-- Show latest readings
SELECT 'Latest Water Quality Reading:' as info;
SELECT * FROM latest_readings;

-- Show active schedules
SELECT 'Active Schedules:' as info;
SELECT * FROM active_schedules;

-- Show relay status
SELECT 'Current Relay Status:' as info;
SELECT * FROM relay_status_summary; 