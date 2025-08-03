-- =====================================================
-- Schedule Logs Table for Water Quality Monitoring System
-- Version: 3.1.0
-- Created: December 2024
-- =====================================================

USE `water_quality_db`;

-- Drop table if exists (for clean installation)
DROP TABLE IF EXISTS `schedule_logs`;

-- Create schedule_logs table
CREATE TABLE `schedule_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schedule_id` int(11) NOT NULL COMMENT 'Reference to relay_schedules table',
  `relay_number` int(11) NOT NULL COMMENT 'Relay channel number (1-4)',
  `action` tinyint(1) NOT NULL COMMENT 'Action performed: 1 = ON, 0 = OFF',
  `execution_time` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Execution timestamp',
  `success` tinyint(1) NOT NULL COMMENT 'Execution success: 1 = success, 0 = failed',
  `error_message` text DEFAULT NULL COMMENT 'Error message if execution failed',
  PRIMARY KEY (`id`),
  KEY `idx_schedule_id` (`schedule_id`),
  KEY `idx_relay_number` (`relay_number`),
  KEY `idx_action` (`action`),
  KEY `idx_execution_time` (`execution_time`),
  KEY `idx_success` (`success`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample schedule logs
INSERT INTO `schedule_logs` (`schedule_id`, `relay_number`, `action`, `execution_time`, `success`, `error_message`) VALUES
(1, 1, 1, '2025-08-03 08:00:01', 1, NULL),
(2, 1, 0, '2025-08-03 18:00:01', 1, NULL),
(3, 2, 1, '2025-08-03 09:00:01', 1, NULL),
(4, 2, 0, '2025-08-03 10:00:01', 1, NULL),
(5, 1, 1, '2025-08-03 15:06:31', 1, NULL);

-- Note: This table tracks all schedule executions for audit and debugging purposes 