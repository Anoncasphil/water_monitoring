-- =====================================================
-- Activity Logs Table for Water Quality Monitoring System
-- Version: 3.1.0
-- Created: December 2024
-- =====================================================

USE `water_quality_db`;

-- Drop table if exists (for clean installation)
DROP TABLE IF EXISTS `activity_logs`;

-- Create activity_logs table
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT 'Reference to users table',
  `action_type` varchar(50) NOT NULL COMMENT 'Type of action performed',
  `performed_by` varchar(100) NOT NULL COMMENT 'Name of user who performed the action',
  `message` text NOT NULL COMMENT 'Action description',
  `details` text DEFAULT NULL COMMENT 'Additional action details',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Action timestamp',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_performed_by` (`performed_by`),
  KEY `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample activity logs
INSERT INTO `activity_logs` (`user_id`, `action_type`, `performed_by`, `message`, `details`, `timestamp`) VALUES
(1, 'user_created', 'System Administrator', 'Created new user account', 'User: admin@waterquality.com with role: admin', '2025-08-01 16:58:39'),
(1, 'user_created', 'System Administrator', 'Created new user account', 'User: john.doe@waterquality.com with role: staff', '2025-08-01 17:01:47'),
(1, 'user_archived', 'System Administrator', 'Archived user account', 'User: test@example.com - Account deactivated', '2025-08-01 17:04:57'),
(1, 'user_activated', 'System Administrator', 'Activated user account', 'User: test@example.com - Account activated', '2025-08-01 17:05:10'),
(1, 'user_updated', 'System Administrator', 'Updated user profile information', 'User: admin@waterquality.com with role: admin', '2025-08-01 17:07:02');

-- Note: This table provides a complete audit trail of all user activities 