<?php
/**
 * Test Schedule Execution
 * 
 * This script helps debug schedule execution issues.
 * Run this to see what's happening with your schedules.
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Schedule Test Started: " . date('Y-m-d H:i:s') . " ===\n\n";

require_once '../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check if relay_schedules table exists
    $result = $conn->query("SHOW TABLES LIKE 'relay_schedules'");
    if ($result->num_rows == 0) {
        echo "❌ relay_schedules table does not exist!\n";
        echo "Creating table...\n";
        
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
        echo "✅ Table created successfully!\n\n";
    } else {
        echo "✅ relay_schedules table exists\n\n";
    }
    
    // Check if relay_states table exists
    $result = $conn->query("SHOW TABLES LIKE 'relay_states'");
    if ($result->num_rows == 0) {
        echo "❌ relay_states table does not exist!\n";
        echo "Creating table...\n";
        
        $createRelayTableSQL = "
        CREATE TABLE IF NOT EXISTS relay_states (
            id INT AUTO_INCREMENT PRIMARY KEY,
            relay_number INT NOT NULL,
            state TINYINT(1) NOT NULL,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $conn->query($createRelayTableSQL);
        
        // Insert default relay states
        for ($i = 1; $i <= 4; $i++) {
            $conn->query("INSERT INTO relay_states (relay_number, state) VALUES ($i, 0)");
        }
        
        echo "✅ relay_states table created with default states!\n\n";
    } else {
        echo "✅ relay_states table exists\n\n";
    }
    
    // Show all schedules
    $result = $conn->query("SELECT * FROM relay_schedules ORDER BY schedule_date ASC, schedule_time ASC");
    $schedules = $result->fetch_all(MYSQLI_ASSOC);
    
    echo "📋 Total schedules found: " . count($schedules) . "\n\n";
    
    if (count($schedules) == 0) {
        echo "❌ No schedules found! Create some schedules first.\n\n";
        
        // Create a test schedule for 2 minutes from now
        $test_time = date('H:i:s', strtotime('+2 minutes'));
        $test_date = date('Y-m-d');
        
        $insertSQL = "INSERT INTO relay_schedules (relay_number, action, schedule_date, schedule_time, frequency, description) 
                      VALUES (1, 1, '$test_date', '$test_time', 'once', 'Test schedule - created automatically')";
        
        if ($conn->query($insertSQL)) {
            echo "✅ Created test schedule for relay 1 (ON) at $test_time today\n";
            echo "   This schedule should execute in about 2 minutes\n\n";
        } else {
            echo "❌ Failed to create test schedule: " . $conn->error . "\n\n";
        }
    } else {
        echo "📅 Current schedules:\n";
        foreach ($schedules as $schedule) {
            $status = $schedule['is_active'] ? '🟢 Active' : '🔴 Inactive';
            $action = $schedule['action'] == 1 ? 'ON' : 'OFF';
            $last_exec = $schedule['last_executed'] ? $schedule['last_executed'] : 'Never';
            
            echo "   ID {$schedule['id']}: Relay {$schedule['relay_number']} -> $action at {$schedule['schedule_time']} on {$schedule['schedule_date']} ($status)\n";
            echo "      Last executed: $last_exec\n";
            echo "      Description: {$schedule['description']}\n\n";
        }
    }
    
    // Check which schedules should execute now
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    
    echo "🔍 Checking for schedules that should execute now...\n";
    echo "   Current time: $now\n";
    echo "   Today's date: $today\n\n";
    
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
    
    echo "⏰ Schedules pending execution: " . count($pending_schedules) . "\n\n";
    
    if (count($pending_schedules) > 0) {
        foreach ($pending_schedules as $schedule) {
            $schedule_datetime = $schedule['schedule_date'] . ' ' . $schedule['schedule_time'];
            $schedule_timestamp = strtotime($schedule_datetime);
            $current_timestamp = time();
            $time_diff = $schedule_timestamp - $current_timestamp;
            
            $action = $schedule['action'] == 1 ? 'ON' : 'OFF';
            
            if ($time_diff <= 0) {
                echo "🚀 READY TO EXECUTE: Schedule ID {$schedule['id']} (Relay {$schedule['relay_number']} -> $action)\n";
                echo "   Scheduled for: $schedule_datetime\n";
                echo "   Overdue by: " . abs($time_diff) . " seconds\n\n";
            } else {
                echo "⏳ WAITING: Schedule ID {$schedule['id']} (Relay {$schedule['relay_number']} -> $action)\n";
                echo "   Scheduled for: $schedule_datetime\n";
                echo "   Will execute in: $time_diff seconds (" . round($time_diff/60, 1) . " minutes)\n\n";
            }
        }
    } else {
        echo "✅ No schedules pending execution\n\n";
    }
    
    // Test relay control API
    echo "🔧 Testing relay control API...\n";
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base_path = dirname(dirname($_SERVER['REQUEST_URI'] ?? '/projtest'));
    $relay_control_url = $protocol . '://' . $host . $base_path . '/api/relay_control.php';
    
    if (empty($host) || $host === 'localhost') {
        $relay_control_url = 'http://localhost/projtest/api/relay_control.php';
    }
    
    echo "   Relay control URL: $relay_control_url\n";
    
    // Test the API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $relay_control_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        echo "   ❌ cURL Error: $curl_error\n";
    } elseif ($http_code !== 200) {
        echo "   ❌ HTTP Error: $http_code\n";
    } else {
        echo "   ✅ Relay control API is accessible\n";
    }
    
    echo "\n=== Test Completed ===\n";
    echo "To run schedules automatically, set up a cron job:\n";
    echo "*/5 * * * * php " . __FILE__ . "\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 