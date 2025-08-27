-- =====================================================
-- Retention Events (run separately after 11_retention_archival.sql)
-- Requires SUPER privilege to create events
-- =====================================================

USE `water_quality_db`;

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
