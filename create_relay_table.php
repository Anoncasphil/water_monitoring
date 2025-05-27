<?php
require_once 'config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Create relay_states table
    $sql = "CREATE TABLE IF NOT EXISTS relay_states (
        relay_number INT NOT NULL PRIMARY KEY,
        state INT NOT NULL DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql)) {
        // Insert initial states for all relays
        $sql = "INSERT IGNORE INTO relay_states (relay_number, state) VALUES 
            (1, 0), (2, 0), (3, 0), (4, 0)";
        
        if ($conn->query($sql)) {
            echo "Relay states table created and initialized successfully";
        } else {
            throw new Exception("Failed to initialize relay states");
        }
    } else {
        throw new Exception("Failed to create relay states table");
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 