<?php
// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

require_once '../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Create a schedule for 2 minutes from now
    $schedule_time = date('H:i:s', strtotime('+2 minutes'));
    $schedule_date = date('Y-m-d');
    
    echo "Creating test schedule for: $schedule_date $schedule_time\n";
    
    $stmt = $conn->prepare("
        INSERT INTO relay_schedules 
        (relay_number, action, schedule_date, schedule_time, frequency, is_active, description, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $relay_number = 1;
    $action = 1; // ON
    $frequency = 'once';
    $is_active = 1;
    $description = 'Test schedule - 2 minutes from now';
    
    $stmt->bind_param("iisssss", $relay_number, $action, $schedule_date, $schedule_time, $frequency, $is_active, $description);
    
    if ($stmt->execute()) {
        $schedule_id = $conn->insert_id;
        echo "✅ Test schedule created successfully!\n";
        echo "Schedule ID: $schedule_id\n";
        echo "Will execute at: $schedule_date $schedule_time\n";
        echo "Current time: " . date('Y-m-d H:i:s') . "\n";
        echo "\nTo test automatic execution:\n";
        echo "1. Set up the Windows Task Scheduler as described\n";
        echo "2. Wait 2 minutes for the schedule to execute automatically\n";
        echo "3. Or run 'php execute_schedules.php' manually to test\n";
    } else {
        echo "❌ Failed to create test schedule\n";
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 