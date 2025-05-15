CREATE TABLE IF NOT EXISTS water_quality_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turbidity_ntu FLOAT NOT NULL,
    tds_ppm FLOAT NOT NULL,
    reading_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
); 