-- =====================================================
-- Relay Schedules Table for Water Quality Monitoring System
-- Version: 3.1.0
-- Created: December 2024
-- =====================================================

USE `water_quality_db`;

-- Drop table if exists (for clean installation)
DROP TABLE IF EXISTS `relay_schedules`;

-- Create relay_schedules table
CREATE TABLE `relay_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `relay_number` int(11) NOT NULL COMMENT 'Relay channel number (1-4)',
  `action` tinyint(1) NOT NULL COMMENT 'Action: 1 = ON, 0 = OFF',
  `schedule_date` date NOT NULL COMMENT 'Date for the schedule',
  `schedule_time` time NOT NULL COMMENT 'Time for the schedule',
  `frequency` enum('once','daily','weekly','monthly') DEFAULT 'once' COMMENT 'Schedule frequency',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Schedule status: 1 = active, 0 = inactive',
  `description` text DEFAULT NULL COMMENT 'Schedule description',
  `last_executed` timestamp NULL DEFAULT NULL COMMENT 'Last execution timestamp',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last update timestamp',
  PRIMARY KEY (`id`),
  KEY `idx_relay_number` (`relay_number`),
  KEY `idx_schedule_date` (`schedule_date`),
  KEY `idx_schedule_time` (`schedule_time`),
  KEY `idx_frequency` (`frequency`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_last_executed` (`last_executed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample schedules
INSERT INTO `relay_schedules` (`relay_number`, `action`, `schedule_date`, `schedule_time`, `frequency`, `is_active`, `description`, `created_at`) VALUES
(1, 1, '2025-08-03', '08:00:00', 'daily', 1, 'Daily filter activation', '2025-08-03 07:00:00'),
(1, 0, '2025-08-03', '18:00:00', 'daily', 1, 'Daily filter deactivation', '2025-08-03 07:00:00'),
(2, 1, '2025-08-03', '09:00:00', 'weekly', 1, 'Weekly water dispensing', '2025-08-03 07:00:00'),
(2, 0, '2025-08-03', '10:00:00', 'weekly', 1, 'Weekly water dispensing stop', '2025-08-03 07:00:00');

-- Note: This table is used for the legacy scheduling system
-- New schedules should use the 'schedules' table 