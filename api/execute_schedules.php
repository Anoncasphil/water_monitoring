<?php
/**
 * Execute Scheduled Relay Operations
 * 
 * This script should be called by a cron job to execute scheduled relay operations.
 * Recommended cron schedule: every 5 minutes
 * 
 * Usage: php execute_schedules.php
 */

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

echo "=== Schedule Execution Started: " . date('Y-m-d H:i:s') . " ===\n";

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

function executeRelayControl($relay_number, $action) {
    // Get the current script directory
    $script_dir = dirname(__FILE__);
    
    // Try to determine the correct URL dynamically
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base_path = dirname(dirname($_SERVER['REQUEST_URI'] ?? '/projtest'));
    $relay_control_url = $protocol . '://' . $host . $base_path . '/api/relay_control.php';
    
    // Fallback to localhost if we can't determine the URL
    if (empty($host) || $host === 'localhost') {
        $relay_control_url = 'http://localhost/projtest/api/relay_control.php';
    }
    
    echo "  Calling relay control URL: $relay_control_url\n";
    
    // Prepare the data
    $data = [
        'relay' => $relay_number,
        'state' => $action
    ];
    
    // Use cURL for better error handling
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $relay_control_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        echo "  cURL Error: $curl_error\n";
        return false;
    }
    
    if ($http_code !== 200) {
        echo "  HTTP Error: $http_code\n";
        return false;
    }
    
    $result = json_decode($response, true);
    if ($result && isset($result['success']) && $result['success']) {
        return true;
    } else {
        $error = isset($result['error']) ? $result['error'] : 'Unknown error';
        echo "  API Error: $error\n";
        return false;
    }
}

function logScheduleExecution($conn, $schedule, $success) {
    // Create schedule_logs table if it doesn't exist
    $createLogTableSQL = "
    CREATE TABLE IF NOT EXISTS schedule_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        schedule_id INT NOT NULL,
        relay_number INT NOT NULL,
        action TINYINT(1) NOT NULL,
        execution_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        success TINYINT(1) NOT NULL,
        error_message TEXT,
        FOREIGN KEY (schedule_id) REFERENCES relay_schedules(id) ON DELETE CASCADE
    )";
    
    $conn->query($createLogTableSQL);
    
    // Log the execution
    $stmt = $conn->prepare("
        INSERT INTO schedule_logs (schedule_id, relay_number, action, success)
        VALUES (?, ?, ?, ?)
    ");
    $success_int = $success ? 1 : 0;
    $stmt->bind_param("iiii", $schedule['id'], $schedule['relay_number'], $schedule['action'], $success_int);
    $stmt->execute();
    $stmt->close();
}

// Get the output and log it
$output = ob_get_clean();
echo $output;

// Optionally save to a log file
$log_file = dirname(__FILE__) . '/../logs/schedule_execution.log';
$log_dir = dirname($log_file);

if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

file_put_contents($log_file, $output . "\n", FILE_APPEND | LOCK_EX);
?> 