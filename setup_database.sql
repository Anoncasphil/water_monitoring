-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS water_quality_db;

-- Use the database
USE water_quality_db;

-- Create water_readings table if it doesn't exist
CREATE TABLE IF NOT EXISTS water_readings (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    turbidity FLOAT NOT NULL,
    tds FLOAT NOT NULL,
    ph FLOAT NOT NULL,
    temperature FLOAT NOT NULL,
    `in` FLOAT NOT NULL,
    reading_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_reading_time ON water_readings(reading_time); 