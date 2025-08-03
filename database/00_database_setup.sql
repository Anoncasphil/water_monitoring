-- =====================================================
-- Database Setup for Water Quality Monitoring System
-- Version: 3.1.0
-- Created: December 2024
-- =====================================================

-- Set SQL mode and timezone
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Set character set and collation
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `water_quality_db` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Use the database
USE `water_quality_db`;

-- Create database user (optional - for production)
-- CREATE USER IF NOT EXISTS 'water_quality_user'@'localhost' IDENTIFIED BY 'your_secure_password';
-- GRANT ALL PRIVILEGES ON water_quality_db.* TO 'water_quality_user'@'localhost';
-- FLUSH PRIVILEGES;

-- Set default storage engine
SET default_storage_engine = InnoDB;

-- Enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Create database metadata table
CREATE TABLE IF NOT EXISTS `database_metadata` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL,
  `installed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial metadata
INSERT INTO `database_metadata` (`version`, `description`) VALUES 
('3.1.0', 'Initial database setup for Water Quality Monitoring System');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */; 