-- =====================================================
-- Users Table for Water Quality Monitoring System
-- Version: 3.1.0
-- Created: December 2024
-- =====================================================

USE `water_quality_db`;

-- Drop table if exists (for clean installation)
DROP TABLE IF EXISTS `users`;

-- Create users table
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample users (passwords are bcrypt hashed)
INSERT INTO `users` (`username`, `first_name`, `last_name`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`, `last_login`) VALUES
('admin', 'System', 'Administrator', 'admin@waterquality.com', '$2y$10$db7OarMY1T69Cz8zYDKMAO4OXrxk1Dt3.5kUKTJjwrVJfL4pRmYkq', 'admin', 'active', '2025-08-01 15:25:20', '2025-08-03 05:42:30', '2025-08-03 05:42:30'),
('staff1', 'John', 'Doe', 'john.doe@waterquality.com', '$2y$10$1hVqkYrIZZmkpxM4Gf7oAuUv9tIIeLg2RvJCDGdqMM0nCMsZL8O26', 'staff', 'active', '2025-08-01 16:52:12', '2025-08-01 17:05:10', NULL),
('admin2', 'Jane', 'Smith', 'jane.smith@waterquality.com', '$2y$10$vHHiFI5GivxU54hNChOZvu4a0ZR9LceJwyq7C7dNuZwg4qd7AeIVC', 'admin', 'active', '2025-08-01 16:58:39', '2025-08-01 16:58:39', NULL),
('tech1', 'Mike', 'Johnson', 'mike.johnson@waterquality.com', '$2y$10$YidV5Z8GWRbRVCFTboQ3t.PidqkiQKdxtpQ7FW3/oz0sBMev6h4ca', 'staff', 'active', '2025-08-01 17:01:47', '2025-08-01 17:07:02', NULL);

-- Note: Default password for all users is 'password123'
-- In production, change these passwords immediately after installation 