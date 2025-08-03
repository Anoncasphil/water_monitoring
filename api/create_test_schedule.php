<?php
/**
 * Create Test Schedule
 * 
 * This script creates a test schedule that will execute in the next minute.
 */

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

require_once '../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Create a test schedule for 1 minute from now
    $test_time = date('H:i:s', strtotime('+1 minute'));
    $test_date = date('Y-m-d');
    
    echo "=== Creating Test Schedule ===\n";
    echo "Current time: " . date('Y-m-d H:i:s') . "\n";
    echo "Test schedule time: $test_time\n";
    echo "Test schedule date: $test_date\n\n";
    
    $insertSQL = "INSERT INTO relay_schedules (relay_number, action, schedule_date, schedule_time, frequency, description) 
                  VALUES (1, 1, '$test_date', '$test_time', 'once', 'Test schedule - created automatically')";
    
    if ($conn->query($insertSQL)) {
        $new_id = $conn->insert_id;
        echo "✅ Test schedule created successfully!\n";
        echo "   Schedule ID: $new_id\n";
        echo "   Relay: 1 (Pool to Filter Pump)\n";
        echo "   Action: Turn ON\n";
        echo "   Time: $test_time today\n";
        echo "   Will execute in about 1 minute\n\n";
        
        echo "To test execution:\n";
        echo "1. Wait 1 minute\n";
        echo "2. Run: php api/execute_schedules.php\n";
        echo "3. Or click 'Execute Now' button on the schedule page\n\n";
        
    } else {
        echo "❌ Failed to create test schedule: " . $conn->error . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 