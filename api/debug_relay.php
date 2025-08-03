<?php
// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

require_once '../config/database.php';

echo "=== Debug Relay System ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    echo "✅ Database connection successful\n\n";
    
    // Check if relay_states table exists
    $result = $conn->query("SHOW TABLES LIKE 'relay_states'");
    if ($result->num_rows > 0) {
        echo "✅ relay_states table exists\n";
        
        // Check table structure
        $structure = $conn->query("DESCRIBE relay_states");
        echo "Table structure:\n";
        while ($row = $structure->fetch_assoc()) {
            echo "  " . $row['Field'] . " - " . $row['Type'] . "\n";
        }
        echo "\n";
        
        // Check current data
        $data = $conn->query("SELECT * FROM relay_states");
        echo "Current relay states:\n";
        while ($row = $data->fetch_assoc()) {
            echo "  Relay " . $row['relay_number'] . ": " . ($row['state'] ? 'ON' : 'OFF') . " (Updated: " . $row['last_updated'] . ")\n";
        }
        echo "\n";
        
    } else {
        echo "❌ relay_states table does not exist\n";
    }
    
    // Check if schedule_logs table exists
    $result = $conn->query("SHOW TABLES LIKE 'schedule_logs'");
    if ($result->num_rows > 0) {
        echo "✅ schedule_logs table exists\n";
        
        // Check recent logs
        $logs = $conn->query("SELECT * FROM schedule_logs ORDER BY execution_time DESC LIMIT 3");
        echo "Recent schedule logs:\n";
        while ($log = $logs->fetch_assoc()) {
            echo "  Schedule " . $log['schedule_id'] . ": Relay " . $log['relay_number'] . " -> " . ($log['action'] ? 'ON' : 'OFF') . " (" . ($log['success'] ? 'SUCCESS' : 'FAILED') . ") at " . $log['execution_time'] . "\n";
        }
        echo "\n";
        
    } else {
        echo "❌ schedule_logs table does not exist\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 