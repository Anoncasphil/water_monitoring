-- =====================================================
-- Schedules Table for Water Quality Monitoring System
-- Version: 3.1.0
-- Created: December 2024
-- =====================================================

USE `water_quality_db`;

-- Drop table if exists (for clean installation)
DROP TABLE IF EXISTS `schedules`;

-- Create schedules table
CREATE TABLE `schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Schedule name/description',
  `relay_number` int(11) NOT NULL COMMENT 'Relay channel number (1-4)',
  `action` enum('on','off') NOT NULL COMMENT 'Action to perform: on or off',
  `schedule_type` enum('one-time','recurring') NOT NULL COMMENT 'Schedule type',
  `time` time NOT NULL COMMENT 'Time for the schedule',
  `date` date DEFAULT NULL COMMENT 'Date for one-time schedules',
  `days` varchar(100) DEFAULT NULL COMMENT 'Days for recurring schedules (comma-separated)',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Schedule status: 1 = active, 0 = inactive',
  `last_executed` datetime DEFAULT NULL COMMENT 'Last execution timestamp',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last update timestamp',
  PRIMARY KEY (`id`),
  KEY `idx_relay_number` (`relay_number`),
  KEY `idx_action` (`action`),
  KEY `idx_schedule_type` (`schedule_type`),
  KEY `idx_time` (`time`),
  KEY `idx_date` (`date`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_last_executed` (`last_executed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample schedules
INSERT INTO `schedules` (`name`, `relay_number`, `action`, `schedule_type`, `time`, `date`, `days`, `is_active`, `created_at`) VALUES
('Morning Filter Start', 1, 'on', 'recurring', '08:00:00', NULL, '1,2,3,4,5,6,7', 1, '2025-08-03 07:00:00'),
('Evening Filter Stop', 1, 'off', 'recurring', '18:00:00', NULL, '1,2,3,4,5,6,7', 1, '2025-08-03 07:00:00'),
('Weekly Water Dispense', 2, 'on', 'recurring', '09:00:00', NULL, '1', 1, '2025-08-03 07:00:00'),
('Water Dispense Stop', 2, 'off', 'recurring', '10:00:00', NULL, '1', 1, '2025-08-03 07:00:00'),
('Test Schedule', 1, 'on', 'one-time', '15:06:00', '2025-08-03', NULL, 1, '2025-08-03 07:05:30');

-- Note: This is the main scheduling system used by the application
-- The 'relay_schedules' table is kept for backward compatibility 