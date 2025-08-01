-- Create activity_logs table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action_type VARCHAR(50) NOT NULL,
    performed_by VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    details TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_timestamp (timestamp),
    INDEX idx_performed_by (performed_by),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some sample activity logs for testing
INSERT INTO activity_logs (user_id, action_type, performed_by, message, details, timestamp) VALUES
(1, 'login', 'John Doe', 'User logged in successfully', 'Login successful', NOW() - INTERVAL 1 HOUR),
(1, 'user_created', 'John Doe', 'Created new user account', 'User: john.doe@example.com with role: user', NOW() - INTERVAL 2 HOUR),
(1, 'user_updated', 'John Doe', 'Updated user profile information', 'User: jane.smith@example.com - Updated email and role', NOW() - INTERVAL 3 HOUR),
(1, 'user_archived', 'John Doe', 'Archived user account', 'User: old.user@example.com - Account deactivated', NOW() - INTERVAL 4 HOUR),
(1, 'relay_control', 'John Doe', 'Relay control operation', 'Relay 1 turned ON - Pool to Filter pump activated', NOW() - INTERVAL 5 HOUR),
(1, 'relay_control', 'John Doe', 'Relay control operation', 'Relay 2 turned OFF - Filter to Pool pump deactivated', NOW() - INTERVAL 6 HOUR),
(1, 'system_config', 'John Doe', 'System configuration updated', 'Updated water quality thresholds and alert settings', NOW() - INTERVAL 7 HOUR),
(1, 'logout', 'John Doe', 'User logged out', 'Logout successful', NOW() - INTERVAL 8 HOUR); 