-- =====================================================
-- Relay States Table for Water Quality Monitoring System
-- Version: 3.1.0
-- Created: December 2024
-- =====================================================

USE `water_quality_db`;

-- Drop table if exists (for clean installation)
DROP TABLE IF EXISTS `relay_states`;

-- Create relay_states table
CREATE TABLE `relay_states` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `relay_number` int(11) NOT NULL COMMENT 'Relay channel number (1-4)',
  `state` tinyint(1) NOT NULL COMMENT 'Relay state: 1 = ON, 0 = OFF',
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last state change timestamp',
  PRIMARY KEY (`id`),
  UNIQUE KEY `relay_number` (`relay_number`),
  KEY `idx_relay_number` (`relay_number`),
  KEY `idx_state` (`state`),
  KEY `idx_last_updated` (`last_updated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert initial relay states (all OFF by default)
INSERT INTO `relay_states` (`relay_number`, `state`, `last_updated`) VALUES
(1, 0, '2025-08-03 15:07:20'),
(2, 0, '2025-08-03 15:07:20'),
(3, 0, '2025-08-03 15:07:20'),
(4, 0, '2025-08-03 15:07:20');

-- Note: Relay 1 = Filter, Relay 2 = Dispense Water, Relays 3-4 = Reserved 