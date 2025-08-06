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
        error_message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_schedule_id (schedule_id),
        INDEX idx_executed_time (executed_time),
        INDEX idx_success (success)
    )";
    
    $conn->query($createLogsTableSQL);
    
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    
    $executed = 0;
    $errors = 0;
    $total_executed = 0;
    $total_errors = 0;
    $execution_cycle = 0;
    $max_cycles = 10; // Prevent infinite loops
    
    echo "Starting continuous execution until no more schedules are due...\n";
    
    do {
        $execution_cycle++;
        $schedules_to_execute = []; // Collect all schedules that should be executed
        $cycle_executed = 0;
        $cycle_errors = 0;
        
        echo "\n--- Execution Cycle $execution_cycle ---\n";
        echo "Current time: " . date('Y-m-d H:i:s T') . "\n";
        
        // Get all active schedules that should be executed
        $stmt = $conn->prepare("
            SELECT * FROM relay_schedules 
            WHERE is_active = 1 
            AND schedule_date <= ? 
            AND CONCAT(schedule_date, ' ', schedule_time) <= ?
            ORDER BY schedule_date ASC, schedule_time ASC
        ");
        $stmt->bind_param("ss", $today, $now);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // First pass: collect all schedules that should be executed
        while ($schedule = $result->fetch_assoc()) {
            $schedule_datetime = $schedule['schedule_date'] . ' ' . $schedule['schedule_time'];
            
            // Check if this schedule should be executed (not executed yet or due for re-execution)
            $should_execute = false;
            
            if ($schedule['last_executed'] === null) {
                // Never executed before
                $should_execute = true;
            } else {
                // Check if it's time to execute again based on frequency
                $last_executed_time = strtotime($schedule['last_executed']);
                $scheduled_time = strtotime($schedule_datetime);
                
                if ($schedule['frequency'] === 'once') {
                    // One-time schedules should only execute if not executed before
                    $should_execute = ($last_executed_time < $scheduled_time);
                } else {
                    // Recurring schedules should execute if the scheduled time has passed since last execution
                    $should_execute = ($last_executed_time < $scheduled_time);
                }
            }
            
            if ($should_execute) {
                $schedules_to_execute[] = $schedule;
                echo "Queued schedule ID {$schedule['id']}: Relay {$schedule['relay_number']} -> " . 
                     ($schedule['action'] == 1 ? 'ON' : 'OFF') . " (Scheduled: $schedule_datetime)\n";
            } else {
                echo "Skipping schedule ID {$schedule['id']}: Already executed or not due yet (Scheduled: $schedule_datetime, Last: {$schedule['last_executed']})\n";
            }
        }
        $stmt->close();
        
        // If no schedules to execute, break the loop
        if (empty($schedules_to_execute)) {
            echo "No more schedules to execute. Ending continuous execution.\n";
            break;
        }
        
        // Group schedules by time for simultaneous execution
        $schedules_by_time = [];
        foreach ($schedules_to_execute as $schedule) {
            $schedule_datetime = $schedule['schedule_date'] . ' ' . $schedule['schedule_time'];
            $schedules_by_time[$schedule_datetime][] = $schedule;
        }
        
        // Execute all schedules grouped by time
        foreach ($schedules_by_time as $schedule_time => $schedules_for_time) {
            echo "\n🕐 Executing ALL schedules for time: $schedule_time";
            echo "\nFound " . count($schedules_for_time) . " schedule(s) to execute simultaneously\n";
            
            // Execute all schedules for this time simultaneously
            $execution_results = [];
            $successful_executions = [];
            $failed_executions = [];
            
            // Execute all schedules for this time
            foreach ($schedules_for_time as $schedule) {
                echo "  Executing schedule ID {$schedule['id']}: Relay {$schedule['relay_number']} -> " . 
                     ($schedule['action'] == 1 ? 'ON' : 'OFF') . "\n";
                
                // Execute the relay control
                $relay_control_result = executeRelayControl($schedule['relay_number'], $schedule['action']);
                
                $execution_results[] = [
                    'schedule' => $schedule,
                    'result' => $relay_control_result
                ];
                
                if ($relay_control_result['success']) {
                    $successful_executions[] = $schedule;
                    echo "    ✓ Successfully executed\n";
                } else {
                    $failed_executions[] = $schedule;
                    echo "    ✗ Failed to execute\n";
                }
            }
            
            echo "  📊 Execution Results Summary:\n";
            echo "    - Successful executions: " . count($successful_executions) . "\n";
            echo "    - Failed executions: " . count($failed_executions) . "\n";
            
            // Log all executions for this time
            echo "  📝 Starting to log executions...\n";
            foreach ($execution_results as $execution) {
                $schedule = $execution['schedule'];
                $result = $execution['result'];
                
                echo "    Logging schedule ID {$schedule['id']} - Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
                
                if ($result['success']) {
                    $cycle_executed++;
                    $total_executed++;
                    
                    // Log the execution
                    logScheduleExecution($conn, $schedule, true);
                } else {
                    $cycle_errors++;
                    $total_errors++;
                    
                    // Log the failure with detailed error message
                    $error_message = $result['error'] ?? "Unknown error occurred";
                    logScheduleExecution($conn, $schedule, false, $error_message);
                }
            }
            echo "  ✅ Finished logging all executions\n";
            
            // Update last_executed timestamps for all successful executions AFTER all schedules for this time are processed
            echo "  🔄 Starting to update last_executed timestamps...\n";
            foreach ($successful_executions as $schedule) {
                echo "    Updating last_executed for schedule ID {$schedule['id']} to: $now\n";
                
                // Update last_executed for all successful schedules (including one-time schedules before deletion)
                $update_stmt = $conn->prepare("UPDATE relay_schedules SET last_executed = ? WHERE id = ?");
                $update_stmt->bind_param("si", $now, $schedule['id']);
                $update_result = $update_stmt->execute();
                $affected_rows = $update_stmt->affected_rows;
                $update_stmt->close();
                
                if ($update_result && $affected_rows > 0) {
                    echo "      ✅ Successfully updated last_executed (affected rows: $affected_rows)\n";
                } else {
                    echo "      ❌ Failed to update last_executed (affected rows: $affected_rows)\n";
                }
                
                // If it's a one-time schedule, remove it after updating last_executed
                if ($schedule['frequency'] === 'once') {
                    echo "      🗑️ Removing one-time schedule ID {$schedule['id']}\n";
                    $delete_stmt = $conn->prepare("DELETE FROM relay_schedules WHERE id = ?");
                    $delete_stmt->bind_param("i", $schedule['id']);
                    $delete_result = $delete_stmt->execute();
                    $affected_rows = $delete_stmt->affected_rows;
                    $delete_stmt->close();
                    
                    if ($delete_result && $affected_rows > 0) {
                        echo "        ✅ Successfully removed one-time schedule (affected rows: $affected_rows)\n";
                    } else {
                        echo "        ❌ Failed to remove one-time schedule (affected rows: $affected_rows)\n";
                    }
                }
            }
            echo "  ✅ Finished updating last_executed timestamps\n";
            
            echo "  ✅ Completed execution for time: $schedule_time";
            echo "\n  Summary: " . count($successful_executions) . " successful, " . count($failed_executions) . " failed\n";
        }
        
        echo "Cycle $execution_cycle completed: $cycle_executed executed, $cycle_errors errors\n";
        
        // Small delay to prevent overwhelming the system
        if (!empty($schedules_to_execute)) {
            echo "Waiting 2 seconds before next cycle...\n";
            sleep(2);
        }
        
        // Update current time for next cycle
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        
    } while (!empty($schedules_to_execute) && $execution_cycle < $max_cycles);
    
    if ($execution_cycle >= $max_cycles) {
        echo "\n⚠️ Maximum execution cycles ($max_cycles) reached. Stopping to prevent infinite loop.\n";
    }
    
    echo "\n=== Final Execution Summary ===\n";
    echo "Total execution cycles: $execution_cycle\n";
    echo "Total schedules executed: $total_executed\n";
    echo "Total errors: $total_errors\n";
    echo "Execution completed at: " . date('Y-m-d H:i:s T') . " (Asia/Manila)\n";
    
    // Show current state of schedules and logs
    echo "\n=== Current Database State ===\n";
    
    // Check relay_schedules
    $schedules_result = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN last_executed IS NOT NULL THEN 1 ELSE 0 END) as executed FROM relay_schedules");
    if ($schedules_result) {
        $schedules_data = $schedules_result->fetch_assoc();
        echo "Relay Schedules: {$schedules_data['total']} total, {$schedules_data['executed']} with last_executed\n";
    }
    
    // Check schedule_logs
    $logs_result = $conn->query("SELECT COUNT(*) as total FROM schedule_logs");
    if ($logs_result) {
        $logs_data = $logs_result->fetch_assoc();
        echo "Schedule Logs: {$logs_data['total']} total entries\n";
    }
    
    // Show recent logs
    $recent_logs = $conn->query("SELECT schedule_id, relay_number, action, scheduled_time, executed_time, success FROM schedule_logs ORDER BY executed_time DESC LIMIT 3");
    if ($recent_logs && $recent_logs->num_rows > 0) {
        echo "Recent Logs:\n";
        while ($log = $recent_logs->fetch_assoc()) {
            echo "  - Schedule ID {$log['schedule_id']}: Relay {$log['relay_number']} -> " . 
                 ($log['action'] == 1 ? 'ON' : 'OFF') . " (Success: " . ($log['success'] ? 'Yes' : 'No') . ")\n";
        }
    }
    
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
    // Use the correct table structure based on the actual database schema
    $scheduled_datetime = $schedule['schedule_date'] . ' ' . $schedule['schedule_time'];
    $executed_datetime = date('Y-m-d H:i:s');
    
    echo "      📝 Attempting to log execution for schedule ID {$schedule['id']}\n";
    echo "        - Scheduled: $scheduled_datetime\n";
    echo "        - Executed: $executed_datetime\n";
    echo "        - Success: " . ($success ? 'Yes' : 'No') . "\n";
    if ($error_message) {
        echo "        - Error: $error_message\n";
    }
    
    $log_sql = "
    INSERT INTO schedule_logs (schedule_id, relay_number, action, scheduled_time, executed_time, success, error_message)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ";
    
    $success_int = $success ? 1 : 0;
    
    $stmt = $conn->prepare($log_sql);
    if (!$stmt) {
        echo "        ❌ Failed to prepare statement: " . $conn->error . "\n";
        return;
    }
    
    $bind_result = $stmt->bind_param("iisssis", 
        $schedule['id'], 
        $schedule['relay_number'], 
        $schedule['action'], 
        $scheduled_datetime,
        $executed_datetime,
        $success_int, 
        $error_message
    );
    
    if (!$bind_result) {
        echo "        ❌ Failed to bind parameters: " . $stmt->error . "\n";
        $stmt->close();
        return;
    }
    
    $execute_result = $stmt->execute();
    if ($execute_result) {
        $insert_id = $stmt->insert_id;
        echo "        ✅ Successfully logged execution (insert ID: $insert_id)\n";
    } else {
        echo "        ❌ Failed to execute log insert: " . $stmt->error . "\n";
    }
    
    $stmt->close();
}
?> 