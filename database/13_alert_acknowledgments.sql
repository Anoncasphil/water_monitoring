-- Alert Acknowledgments Table
-- This table stores user acknowledgments of water quality alerts

CREATE TABLE IF NOT EXISTS alert_acknowledgments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type ENUM('turbidity', 'tds') NOT NULL COMMENT 'Type of alert that was acknowledged',
    alert_message TEXT NOT NULL COMMENT 'The original alert message',
    action_taken VARCHAR(50) NOT NULL COMMENT 'Action taken to address the alert',
    details TEXT NOT NULL COMMENT 'Detailed description of actions taken',
    responsible_person VARCHAR(100) COMMENT 'Name or ID of person who acknowledged',
    sensor_values JSON COMMENT 'Sensor readings at time of alert',
    acknowledged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When the alert was acknowledged',
    alert_timestamp TIMESTAMP COMMENT 'When the original alert occurred',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
    
    -- Indexes for performance
    INDEX idx_alert_type (alert_type),
    INDEX idx_acknowledged_at (acknowledged_at),
    INDEX idx_alert_timestamp (alert_timestamp),
    INDEX idx_action_taken (action_taken),
    
    -- Constraints
    CONSTRAINT chk_action_taken CHECK (action_taken IN (
        'filter_replacement', 'system_maintenance', 'chemical_treatment',
        'system_flush', 'investigation', 'manual_intervention', 'other'
    ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores user acknowledgments of water quality alerts with action details';

-- Add sample data for testing (optional)
-- INSERT INTO alert_acknowledgments (alert_type, alert_message, action_taken, details, responsible_person, alert_timestamp) VALUES
-- ('turbidity', 'High turbidity (25.5 NTU) - Water is very cloudy and may contain harmful particles', 'filter_replacement', 'Replaced primary sediment filter and flushed system for 10 minutes', 'John Doe', '2024-01-15 14:30:00'),
-- ('tds', 'High TDS (1200 ppm) - Water contains excessive dissolved solids', 'system_flush', 'Performed complete system flush and added fresh water', 'Jane Smith', '2024-01-15 16:45:00');
