<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Define water quality thresholds
define('TDS_CRITICAL_MIN', 200);  // ppm - Critical range starts at 200
define('TDS_CRITICAL_MAX', 500);  // ppm - Critical range ends at 500
define('TDS_MEDIUM_MIN', 150);    // ppm - Medium range starts at 150
define('TDS_MEDIUM_MAX', 200);    // ppm - Medium range ends at 200

define('TURBIDITY_CRITICAL_MIN', 10.0);  // NTU - Critical range starts at 10.0 (EPA limit)
define('TURBIDITY_CRITICAL_MAX', 50.0);  // NTU - Critical range ends at 50.0
define('TURBIDITY_MEDIUM_MIN', 5.0);     // NTU - Medium range starts at 5.0
define('TURBIDITY_MEDIUM_MAX', 10.0);    // NTU - Medium range ends at 10.0

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Handle GET request for automation status and sensor data
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get latest sensor readings
        $result = $conn->query("SELECT turbidity, tds, ph, temperature, reading_time FROM water_readings ORDER BY reading_time DESC LIMIT 1");
        $latest_reading = $result->fetch_assoc();
        
        // Get current relay states
        $relay_result = $conn->query("SELECT relay_number, state FROM relay_states ORDER BY relay_number");
        $relay_states = [];
        while ($row = $relay_result->fetch_assoc()) {
            $relay_states[] = [
                "relay_number" => (int)$row['relay_number'],
                "state" => (int)$row['state']
            ];
        }
        
        // Get automation settings
        $automation_result = $conn->query("SELECT * FROM automation_settings WHERE id = 1");
        $automation_settings = $automation_result->fetch_assoc();
        
        if (!$automation_settings) {
            // Create default automation settings if they don't exist
            $conn->query("INSERT INTO automation_settings (id, enabled, filter_auto_enabled, last_check, created_at) VALUES (1, 1, 1, NOW(), NOW())");
            $automation_settings = [
                'enabled' => 1,
                'filter_auto_enabled' => 1,
                'last_check' => date('Y-m-d H:i:s')
            ];
        }
        
        // Analyze sensor data for automation triggers
        $analysis = analyzeSensorData($latest_reading);
        
        echo json_encode([
            "success" => true,
            "sensor_data" => $latest_reading,
            "relay_states" => $relay_states,
            "automation_settings" => $automation_settings,
            "analysis" => $analysis
        ]);
        exit;
    }

    // Handle POST request for updating automation settings
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        
        if ($action === 'update_settings') {
            $enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 0;
            $filter_auto_enabled = isset($_POST['filter_auto_enabled']) ? (int)$_POST['filter_auto_enabled'] : 0;
            
            // Update automation settings
            $stmt = $conn->prepare("UPDATE automation_settings SET enabled = ?, filter_auto_enabled = ?, updated_at = NOW() WHERE id = 1");
            $stmt->bind_param("ii", $enabled, $filter_auto_enabled);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update automation settings");
            }
            
            echo json_encode([
                "success" => true,
                "message" => "Automation settings updated successfully"
            ]);
        } elseif ($action === 'check_and_trigger') {
            // Manual trigger to check sensors and apply automation
            $result = checkAndTriggerAutomation($conn);
            echo json_encode($result);
        } else {
            throw new Exception("Invalid action");
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage()
    ]);
}

/**
 * Analyze sensor data and determine if automation should trigger
 */
function analyzeSensorData($sensor_data) {
    if (!$sensor_data) {
        return [
            'tds_status' => 'unknown',
            'turbidity_status' => 'unknown',
            'should_activate_filter' => false,
            'reason' => 'No sensor data available'
        ];
    }
    
    $tds = $sensor_data['tds'];
    $turbidity = $sensor_data['turbidity'];
    
    // Determine TDS status
    $tds_status = 'normal';
    if ($tds >= TDS_CRITICAL_MIN && $tds <= TDS_CRITICAL_MAX) {
        $tds_status = 'critical';
    } elseif ($tds >= TDS_MEDIUM_MIN && $tds <= TDS_MEDIUM_MAX) {
        $tds_status = 'medium';
    }
    
    // Determine turbidity status
    $turbidity_status = 'normal';
    if ($turbidity >= TURBIDITY_CRITICAL_MIN && $turbidity <= TURBIDITY_CRITICAL_MAX) {
        $turbidity_status = 'critical';
    } elseif ($turbidity >= TURBIDITY_MEDIUM_MIN && $turbidity <= TURBIDITY_MEDIUM_MAX) {
        $turbidity_status = 'medium';
    }
    
    // Check if filter should be activated
    $should_activate_filter = false;
    $reason = '';
    
    if (($tds_status === 'critical' || $tds_status === 'medium') && 
        ($turbidity_status === 'critical' || $turbidity_status === 'medium')) {
        $should_activate_filter = true;
        $reason = "Both TDS ({$tds} ppm, {$tds_status}) and Turbidity ({$turbidity} NTU, {$turbidity_status}) are in critical/medium range";
    } elseif ($tds_status === 'critical' || $turbidity_status === 'critical') {
        $should_activate_filter = true;
        $reason = "Critical levels detected: TDS ({$tds} ppm, {$tds_status}), Turbidity ({$turbidity} NTU, {$turbidity_status})";
    }
    
    return [
        'tds_status' => $tds_status,
        'turbidity_status' => $turbidity_status,
        'should_activate_filter' => $should_activate_filter,
        'reason' => $reason,
        'tds_value' => $tds,
        'turbidity_value' => $turbidity
    ];
}

/**
 * Check sensor data and trigger automation if needed
 */
function checkAndTriggerAutomation($conn) {
    // Get latest sensor readings
    $result = $conn->query("SELECT turbidity, tds, ph, temperature, reading_time FROM water_readings ORDER BY reading_time DESC LIMIT 1");
    $latest_reading = $result->fetch_assoc();
    
    if (!$latest_reading) {
        return [
            "success" => false,
            "message" => "No sensor data available"
        ];
    }
    
    // Analyze sensor data
    $analysis = analyzeSensorData($latest_reading);
    
    // Get current relay state for filter (relay 1)
    $relay_result = $conn->query("SELECT state FROM relay_states WHERE relay_number = 1");
    $current_filter_state = $relay_result->fetch_assoc();
    $filter_currently_on = $current_filter_state && $current_filter_state['state'] == 1;
    
    // Check if automation is enabled
    $automation_result = $conn->query("SELECT enabled, filter_auto_enabled FROM automation_settings WHERE id = 1");
    $automation_settings = $automation_result->fetch_assoc();
    
    if (!$automation_settings || !$automation_settings['enabled'] || !$automation_settings['filter_auto_enabled']) {
        return [
            "success" => true,
            "message" => "Automation is disabled",
            "analysis" => $analysis,
            "action_taken" => "none"
        ];
    }
    
    // Determine action based on analysis
    $action_taken = "none";
    $new_filter_state = $filter_currently_on ? 1 : 0;
    
    if ($analysis['should_activate_filter'] && !$filter_currently_on) {
        // Activate filter
        $stmt = $conn->prepare("UPDATE relay_states SET state = 1 WHERE relay_number = 1");
        $stmt->execute();
        $new_filter_state = 1;
        $action_taken = "filter_activated";
        
        // Log the automation action
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $system_user_id = 0; // System user
        $action_desc = "Automation: Filter activated due to water quality";
        $details = $analysis['reason'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt->bind_param("isss", $system_user_id, $action_desc, $details, $ip);
        $stmt->execute();
        
    } elseif (!$analysis['should_activate_filter'] && $filter_currently_on) {
        // Deactivate filter if conditions are now normal
        $stmt = $conn->prepare("UPDATE relay_states SET state = 0 WHERE relay_number = 1");
        $stmt->execute();
        $new_filter_state = 0;
        $action_taken = "filter_deactivated";
        
        // Log the automation action
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $system_user_id = 0; // System user
        $action_desc = "Automation: Filter deactivated - water quality normal";
        $details = "TDS: {$analysis['tds_value']} ppm ({$analysis['tds_status']}), Turbidity: {$analysis['turbidity_value']} NTU ({$analysis['turbidity_status']})";
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt->bind_param("isss", $system_user_id, $action_desc, $details, $ip);
        $stmt->execute();
    }
    
    // Update last check time
    $conn->query("UPDATE automation_settings SET last_check = NOW() WHERE id = 1");
    
    return [
        "success" => true,
        "message" => "Automation check completed",
        "analysis" => $analysis,
        "action_taken" => $action_taken,
        "filter_state" => $new_filter_state
    ];
}
?> 