-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS water_quality_db;

-- Use the database
USE water_quality_db;

-- Create table for water readings
CREATE TABLE IF NOT EXISTS water_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turbidity FLOAT NOT NULL,
    tds FLOAT NOT NULL,
    reading_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
); 