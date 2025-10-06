-- Migration: Create per-sensor acknowledgment status table (5h window)
-- Safe to run multiple times

CREATE TABLE IF NOT EXISTS sensor_acknowledgments (
    sensor_type ENUM('turbidity','tds','ph') PRIMARY KEY,
    acknowledged_until DATETIME NOT NULL,
    acknowledged_at DATETIME NOT NULL,
    last_action VARCHAR(50) NULL,
    last_person VARCHAR(100) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_until (acknowledged_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional housekeeping: remove expired windows (older than now)
DELETE FROM sensor_acknowledgments WHERE acknowledged_until < NOW();


