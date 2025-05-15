-- Create database
CREATE DATABASE IF NOT EXISTS water_quality_db;
USE water_quality_db;

-- Create water_quality table
CREATE TABLE IF NOT EXISTS water_quality (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turbidity FLOAT NOT NULL,
    tds FLOAT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
); 