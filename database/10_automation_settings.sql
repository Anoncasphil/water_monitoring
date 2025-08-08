-- =====================================================
-- Automation Settings Table for Water Quality System
-- Version: 1.0.0
-- Created: December 2024
-- =====================================================

USE `water_quality_db`;

-- Drop table if exists (for clean installation)
DROP TABLE IF EXISTS `automation_settings`;

-- Create automation_settings table
CREATE TABLE `automation_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Master automation switch (0=disabled, 1=enabled)',
  `filter_auto_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Filter automation switch (0=disabled, 1=enabled)',
  `tds_critical_min` float NOT NULL DEFAULT 200 COMMENT 'TDS critical minimum threshold (ppm)',
  `tds_critical_max` float NOT NULL DEFAULT 500 COMMENT 'TDS critical maximum threshold (ppm)',
  `tds_medium_min` float NOT NULL DEFAULT 150 COMMENT 'TDS medium minimum threshold (ppm)',
  `tds_medium_max` float NOT NULL DEFAULT 200 COMMENT 'TDS medium maximum threshold (ppm)',
  `turbidity_critical_min` float NOT NULL DEFAULT 5.0 COMMENT 'Turbidity critical minimum threshold (NTU)',
  `turbidity_critical_max` float NOT NULL DEFAULT 10.0 COMMENT 'Turbidity critical maximum threshold (NTU)',
  `turbidity_medium_min` float NOT NULL DEFAULT 2.0 COMMENT 'Turbidity medium minimum threshold (NTU)',
  `turbidity_medium_max` float NOT NULL DEFAULT 5.0 COMMENT 'Turbidity medium maximum threshold (NTU)',
  `check_interval` int(11) NOT NULL DEFAULT 30 COMMENT 'Automation check interval in seconds',
  `last_check` timestamp NULL DEFAULT NULL COMMENT 'Last automation check timestamp',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record update timestamp',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default automation settings
INSERT INTO `automation_settings` (
  `id`, 
  `enabled`, 
  `filter_auto_enabled`,
  `tds_critical_min`,
  `tds_critical_max`,
  `tds_medium_min`,
  `tds_medium_max`,
  `turbidity_critical_min`,
  `turbidity_critical_max`,
  `turbidity_medium_min`,
  `turbidity_medium_max`,
  `check_interval`,
  `last_check`,
  `created_at`
) VALUES (
  1, 
  1, 
  1,
  200,
  500,
  150,
  200,
  5.0,
  10.0,
  2.0,
  5.0,
  30,
  NOW(),
  NOW()
);

-- Add indexes for better performance
ALTER TABLE `automation_settings` ADD INDEX `idx_enabled` (`enabled`);
ALTER TABLE `automation_settings` ADD INDEX `idx_last_check` (`last_check`); 