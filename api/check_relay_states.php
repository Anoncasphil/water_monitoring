<?php
// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

require_once '../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "=== Current Relay States ===\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n\n";
    
    $result = $conn->query("SELECT relay_number, state, last_updated FROM relay_states ORDER BY relay_number");
    
    while ($row = $result->fetch_assoc()) {
        $status = $row['state'] ? 'ON' : 'OFF';
        echo "Relay " . $row['relay_number'] . ": " . $status . " (Last updated: " . $row['last_updated'] . ")\n";
    }
    
    echo "\n=== Recent Schedule Logs ===\n";
    $logs = $conn->query("SELECT schedule_id, relay_number, action, success, execution_time FROM schedule_logs ORDER BY execution_time DESC LIMIT 5");
    
    while ($log = $logs->fetch_assoc()) {
        $action = $log['action'] ? 'ON' : 'OFF';
        $success = $log['success'] ? 'SUCCESS' : 'FAILED';
        echo "Schedule " . $log['schedule_id'] . ": Relay " . $log['relay_number'] . " -> " . $action . " (" . $success . ") at " . $log['execution_time'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 