<?php
/**
 * Check Schedule Timing
 * 
 * This script helps debug why schedules aren't executing.
 */

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

require_once '../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    
    echo "=== Schedule Timing Check ===\n";
    echo "Current time: $now\n";
    echo "Today's date: $today\n\n";
    
    // Get all schedules
    $result = $conn->query("SELECT * FROM relay_schedules ORDER BY schedule_date ASC, schedule_time ASC");
    $schedules = $result->fetch_all(MYSQLI_ASSOC);
    
    echo "Total schedules: " . count($schedules) . "\n\n";
    
    foreach ($schedules as $schedule) {
        $schedule_datetime = $schedule['schedule_date'] . ' ' . $schedule['schedule_time'];
        $schedule_timestamp = strtotime($schedule_datetime);
        $current_timestamp = time();
        $time_diff = $schedule_timestamp - $current_timestamp;
        
        $action = $schedule['action'] == 1 ? 'ON' : 'OFF';
        $status = $schedule['is_active'] ? 'Active' : 'Inactive';
        
        echo "Schedule ID {$schedule['id']}:\n";
        echo "  Relay: {$schedule['relay_number']} -> $action\n";
        echo "  Scheduled for: $schedule_datetime\n";
        echo "  Status: $status\n";
        echo "  Time difference: $time_diff seconds (" . round($time_diff/60, 1) . " minutes)\n";
        
        if ($time_diff <= 0) {
            echo "  ✅ READY TO EXECUTE (overdue by " . abs($time_diff) . " seconds)\n";
        } else {
            echo "  ⏳ WAITING (will execute in " . round($time_diff/60, 1) . " minutes)\n";
        }
        
        if ($schedule['last_executed']) {
            echo "  Last executed: {$schedule['last_executed']}\n";
        } else {
            echo "  Last executed: Never\n";
        }
        
        echo "\n";
    }
    
    // Check which schedules should execute now
    $stmt = $conn->prepare("
        SELECT * FROM relay_schedules 
        WHERE is_active = 1 
        AND schedule_date <= ? 
        AND (last_executed IS NULL OR last_executed < CONCAT(schedule_date, ' ', schedule_time))
        ORDER BY schedule_date ASC, schedule_time ASC
    ");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pending_schedules = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo "=== Schedules Ready to Execute ===\n";
    echo "Count: " . count($pending_schedules) . "\n\n";
    
    if (count($pending_schedules) > 0) {
        foreach ($pending_schedules as $schedule) {
            $schedule_datetime = $schedule['schedule_date'] . ' ' . $schedule['schedule_time'];
            $action = $schedule['action'] == 1 ? 'ON' : 'OFF';
            
            echo "🚀 Schedule ID {$schedule['id']}: Relay {$schedule['relay_number']} -> $action\n";
            echo "   Scheduled for: $schedule_datetime\n";
            echo "   Should execute now!\n\n";
        }
    } else {
        echo "No schedules ready to execute.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 