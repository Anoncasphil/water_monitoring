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

-- 4) Events: Note you must have the event scheduler enabled in the server
-- To enable: SET GLOBAL event_scheduler = ON; (requires SUPER privilege)

-- Roll up the last fully completed hour into hourly table
DROP EVENT IF EXISTS `ev_rollup_hourly_water_readings`;
CREATE EVENT `ev_rollup_hourly_water_readings`
ON SCHEDULE EVERY 1 HOUR
DO
INSERT INTO `water_readings_hourly` (`hour_start`, `avg_turbidity`, `avg_tds`, `avg_ph`, `avg_temperature`, `readings`)
SELECT
  DATE_FORMAT(`reading_time`, '%Y-%m-%d %H:00:00') AS hour_start,
  AVG(`turbidity`), AVG(`tds`), AVG(`ph`), AVG(`temperature`), COUNT(*)
FROM `water_readings`
WHERE `reading_time` < DATE_FORMAT(NOW(), '%Y-%m-%d %H:00:00')
  AND `reading_time` >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 HOUR), '%Y-%m-%d %H:00:00')
GROUP BY hour_start
ON DUPLICATE KEY UPDATE
  `avg_turbidity` = VALUES(`avg_turbidity`),
  `avg_tds` = VALUES(`avg_tds`),
  `avg_ph` = VALUES(`avg_ph`),
  `avg_temperature` = VALUES(`avg_temperature`),
  `readings` = VALUES(`readings`);

-- Nightly archival: move rows older than 14 days to archive
DROP EVENT IF EXISTS `ev_archive_old_water_readings`;
CREATE EVENT `ev_archive_old_water_readings`
ON SCHEDULE EVERY 1 DAY
DO
BEGIN
  INSERT INTO `water_readings_archive`
  SELECT * FROM `water_readings`
  WHERE `reading_time` < NOW() - INTERVAL 14 DAY;

  DELETE FROM `water_readings`
  WHERE `reading_time` < NOW() - INTERVAL 14 DAY;
END;


