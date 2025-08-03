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

echo "=== Schedule Execution Started: " . date('Y-m-d H:i:s') . " ===\n";

// Define the base path
$base_path = dirname(__DIR__);
$config_path = $base_path . '/config/database.php';

echo "Base path: $base_path\n";
echo "Config path: $config_path\n";
echo "Config exists: " . (file_exists($config_path) ? 'YES' : 'NO') . "\n";

// Load database configuration
if (!file_exists($config_path)) {
    echo "ERROR: Database config file not found at: $config_path\n";
    exit(1);
}

require_once $config_path;

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
            OR (
                last_executed < CONCAT(schedule_date, ' ', schedule_time)
                AND CONCAT(schedule_date, ' ', schedule_time) <= ?
            )
        )
        ORDER BY schedule_date ASC, schedule_time ASC
    ");
    $stmt->bind_param("ss", $today, $now);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $executed = 0;
    $errors = 0;
    
    while ($schedule = $result->fetch_assoc()) {
        $schedule_datetime = $schedule['schedule_date'] . ' ' . $schedule['schedule_time'];
        
        // Additional check to ensure we only execute schedules that are actually due
        if (strtotime($schedule_datetime) <= time() && 
            ($schedule['last_executed'] === null || 
             strtotime($schedule['last_executed']) < strtotime($schedule_datetime))) {
            echo "Executing schedule ID {$schedule['id']}: Relay {$schedule['relay_number']} -> " . 
                 ($schedule['action'] == 1 ? 'ON' : 'OFF') . " (Scheduled: $schedule_datetime)\n";
            
            // Execute the relay control
            $relay_control_result = executeRelayControl($schedule['relay_number'], $schedule['action']);
            
            if ($relay_control_result) {
                // Update last_executed timestamp
                $update_stmt = $conn->prepare("UPDATE relay_schedules SET last_executed = ? WHERE id = ?");
                $update_stmt->bind_param("si", $now, $schedule['id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                $executed++;
                echo "  ✓ Successfully executed\n";
                
                // Log the execution
                logScheduleExecution($conn, $schedule, true);
            } else {
                $errors++;
                echo "  ✗ Failed to execute\n";
                
                // Log the failure
                logScheduleExecution($conn, $schedule, false);
            }
        }
    }
    $stmt->close();
    
    echo "\n=== Execution Summary ===\n";
    echo "Total schedules executed: $executed\n";
    echo "Total errors: $errors\n";
    echo "Execution completed at: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Schedule execution error: " . $e->getMessage());
}

/**
 * Execute relay control via API
 */
function executeRelayControl($relay_number, $action) {
    $api_url = "http://localhost/projtest/api/relay_control.php";
    
    $post_data = [
        'relay_number' => $relay_number,
        'action' => $action
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
    curl_close($ch);
    
    if ($http_code !== 200) {
        echo "  HTTP Error: $http_code\n";
        return false;
    }
    
    $result = json_decode($response, true);
    return isset($result['success']) && $result['success'];
}

/**
 * Log schedule execution
 */
function logScheduleExecution($conn, $schedule, $success) {
    $log_sql = "
    INSERT INTO schedule_logs (schedule_id, relay_number, action, scheduled_time, executed_time, success, details)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ";
    
    $scheduled_time = $schedule['schedule_date'] . ' ' . $schedule['schedule_time'];
    $executed_time = date('Y-m-d H:i:s');
    $details = $success ? 'Successfully executed' : 'Failed to execute';
    $success_int = $success ? 1 : 0;
    
    $stmt = $conn->prepare($log_sql);
    $stmt->bind_param("iiissis", 
        $schedule['id'], 
        $schedule['relay_number'], 
        $schedule['action'], 
        $scheduled_time, 
        $executed_time, 
        $success_int, 
        $details
    );
    $stmt->execute();
    $stmt->close();
}
?> 