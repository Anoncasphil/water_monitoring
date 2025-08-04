<?php
/**
 * Execute Scheduled Tasks - Fixed Version
 * This script runs scheduled relay control tasks
 * 
 * Recommended cron schedule: every 5 minutes
 * Example: every 5 minutes
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

echo "=== Schedule Execution Started: " . date('Y-m-d H:i:s') . " (Asia/Manila) ===\n";
echo "Current time: " . date('Y-m-d H:i:s T') . "\n";

// Load database configuration
require_once '../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Ensure relay_schedules table exists
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS relay_schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        relay_number INT NOT NULL,
        action TINYINT(1) NOT NULL COMMENT '1 for ON, 0 for OFF',
        schedule_date DATE NOT NULL,
        schedule_time TIME NOT NULL,
        frequency ENUM('once', 'daily', 'weekly', 'monthly') DEFAULT 'once',
        is_active TINYINT(1) DEFAULT 1,
        description TEXT,
        last_executed TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_schedule_time (schedule_date, schedule_time),
        INDEX idx_relay (relay_number),
        INDEX idx_active (is_active)
    )";
    
    $conn->query($createTableSQL);
    
    // Ensure schedule_logs table exists
    $createLogsTableSQL = "
    CREATE TABLE IF NOT EXISTS schedule_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        schedule_id INT NOT NULL,
        relay_number INT NOT NULL,
        action TINYINT(1) NOT NULL,
        scheduled_time DATETIME NOT NULL,
        executed_time DATETIME NOT NULL,
        success TINYINT(1) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_schedule_id (schedule_id),
        INDEX idx_executed_time (executed_time),
        INDEX idx_success (success)
    )";
    
    $conn->query($createLogsTableSQL);
    
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    
    echo "Checking for schedules to execute...\n";
    
    // Get all active schedules that should be executed
    $stmt = $conn->prepare("
        SELECT * FROM relay_schedules 
        WHERE is_active = 1 
        AND schedule_date <= ? 
        AND (
            last_executed IS NULL 
            OR last_executed < CONCAT(schedule_date, ' ', schedule_time)
        )
        AND CONCAT(schedule_date, ' ', schedule_time) <= ?
        ORDER BY schedule_date ASC, schedule_time ASC
    ");
    $stmt->bind_param("ss", $today, $now);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $executed = 0;
    $errors = 0;
    
    while ($schedule = $result->fetch_assoc()) {
        $schedule_datetime = $schedule['schedule_date'] . ' ' . $schedule['schedule_time'];
        
        echo "Executing schedule ID {$schedule['id']}: Relay {$schedule['relay_number']} -> " . 
             ($schedule['action'] == 1 ? 'ON' : 'OFF') . " (Scheduled: $schedule_datetime)\n";
        
        // Execute the relay control
        $relay_control_result = executeRelayControl($schedule['relay_number'], $schedule['action']);
        
        if ($relay_control_result['success']) {
            // Update last_executed timestamp
            $update_stmt = $conn->prepare("UPDATE relay_schedules SET last_executed = ? WHERE id = ?");
            $update_stmt->bind_param("si", $now, $schedule['id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            $executed++;
            echo "  ✓ Successfully executed\n";
            
            // Log the execution
            logScheduleExecution($conn, $schedule, true);
            
            // If it's a one-time schedule, remove it after successful execution
            if ($schedule['frequency'] === 'once') {
                $delete_stmt = $conn->prepare("DELETE FROM relay_schedules WHERE id = ?");
                $delete_stmt->bind_param("i", $schedule['id']);
                $delete_stmt->execute();
                $delete_stmt->close();
                echo "  🗑️ One-time schedule removed (ID: {$schedule['id']})\n";
            }
        } else {
            $errors++;
            echo "  ✗ Failed to execute\n";
            
            // Log the failure with detailed error message
            $error_message = $relay_control_result['error'] ?? "Unknown error occurred";
            logScheduleExecution($conn, $schedule, false, $error_message);
        }
    }
    $stmt->close();
    
    echo "\n=== Execution Summary ===\n";
    echo "Total schedules executed: $executed\n";
    echo "Total errors: $errors\n";
    echo "Execution completed at: " . date('Y-m-d H:i:s T') . " (Asia/Manila)\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Schedule execution error: " . $e->getMessage());
}

/**
 * Execute relay control via API
 */
function executeRelayControl($relay_number, $action) {
    $api_url = "https://waterquality.triple7autosupply.com/api/relay_control.php";
    
    $post_data = [
        'relay' => $relay_number,
        'state' => $action
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code !== 200) {
        echo "  HTTP Error: $http_code\n";
        if ($curl_error) {
            echo "  cURL Error: $curl_error\n";
        }
        return ['success' => false, 'error' => "HTTP Error: $http_code" . ($curl_error ? " - $curl_error" : "")];
    }
    
    $result = json_decode($response, true);
    if (isset($result['success']) && $result['success']) {
        return ['success' => true];
    } else {
        $error_msg = isset($result['error']) ? $result['error'] : 'Unknown API error';
        echo "  API Error: $error_msg\n";
        return ['success' => false, 'error' => $error_msg];
    }
}

/**
 * Log schedule execution
 */
function logScheduleExecution($conn, $schedule, $success, $error_message = null) {
    $log_sql = "
    INSERT INTO schedule_logs (schedule_id, relay_number, action, success, error_message)
    VALUES (?, ?, ?, ?, ?)
    ";
    
    $success_int = $success ? 1 : 0;
    
    $stmt = $conn->prepare($log_sql);
    $stmt->bind_param("iiiss", 
        $schedule['id'], 
        $schedule['relay_number'], 
        $schedule['action'], 
        $success_int, 
        $error_message
    );
    $stmt->execute();
    $stmt->close();
}
?> 