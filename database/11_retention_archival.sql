-- =====================================================
-- Long-term retention: archival, rollups, and unified view
-- Applies on existing schema created by 02_water_readings.sql
-- =====================================================

USE `water_quality_db`;

-- 1) Archive table: same structure as water_readings
CREATE TABLE IF NOT EXISTS `water_readings_archive` LIKE `water_readings`;

-- Ensure time index exists
CREATE INDEX IF NOT EXISTS `idx_wra_time` ON `water_readings_archive`(`reading_time`);

-- 2) Hourly rollup table (compact long-term history)
CREATE TABLE IF NOT EXISTS `water_readings_hourly` (
  `hour_start` DATETIME NOT NULL,
  `avg_turbidity` DECIMAL(10,3) NULL,
  `avg_tds` DECIMAL(10,3) NULL,
  `avg_ph` DECIMAL(10,3) NULL,
  `avg_temperature` DECIMAL(10,3) NULL,
  `readings` INT NULL,
  PRIMARY KEY (`hour_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3) View to query both live and archive seamlessly
CREATE OR REPLACE VIEW `water_readings_all` AS
SELECT * FROM `water_readings`
UNION ALL
SELECT * FROM `water_readings_archive`;

-- Note: To enable automatic archival and rollups, you need to:
-- 1. Enable event scheduler: SET GLOBAL event_scheduler = ON; (requires SUPER privilege)
-- 2. Create the events manually or run them as separate SQL statements


