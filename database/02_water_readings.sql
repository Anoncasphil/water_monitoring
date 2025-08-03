-- =====================================================
-- Water Readings Table for Water Quality Monitoring System
-- Version: 3.1.0
-- Created: December 2024
-- =====================================================

USE `water_quality_db`;

-- Drop table if exists (for clean installation)
DROP TABLE IF EXISTS `water_readings`;

-- Create water_readings table
CREATE TABLE `water_readings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `turbidity` float NOT NULL COMMENT 'Turbidity in NTU (Nephelometric Turbidity Units)',
  `tds` float NOT NULL COMMENT 'Total Dissolved Solids in ppm (parts per million)',
  `ph` float NOT NULL COMMENT 'pH level (0-14 scale)',
  `temperature` float NOT NULL COMMENT 'Temperature in Celsius',
  `in` float NOT NULL DEFAULT 0 COMMENT 'Input voltage reading (deprecated)',
  `reading_time` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Timestamp of sensor reading',
  PRIMARY KEY (`id`),
  KEY `idx_reading_time` (`reading_time`),
  KEY `idx_turbidity` (`turbidity`),
  KEY `idx_tds` (`tds`),
  KEY `idx_ph` (`ph`),
  KEY `idx_temperature` (`temperature`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample water quality readings
INSERT INTO `water_readings` (`turbidity`, `tds`, `ph`, `temperature`, `in`, `reading_time`) VALUES
(1.7, 150, 7.2, 25.5, 0, '2025-05-25 14:03:51'),
(1.5, 145, 7.1, 25.3, 0, '2025-05-25 14:04:00'),
(1.55, 148, 7.3, 25.4, 0, '2025-05-25 14:04:02'),
(1.6, 152, 7.2, 25.6, 0, '2025-05-25 14:04:04'),
(1.4, 140, 7.0, 25.2, 0, '2025-05-25 14:04:06'),
(1.8, 155, 7.4, 25.7, 0, '2025-05-25 14:04:08'),
(1.3, 138, 6.9, 25.1, 0, '2025-05-25 14:04:10'),
(1.9, 158, 7.5, 25.8, 0, '2025-05-25 14:04:12'),
(1.2, 135, 6.8, 25.0, 0, '2025-05-25 14:04:14'),
(2.0, 160, 7.6, 25.9, 0, '2025-05-25 14:04:16');

-- Note: The 'in' column is deprecated and kept for backward compatibility
-- New installations should focus on the main water quality parameters 