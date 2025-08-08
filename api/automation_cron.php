<?php
/**
 * Automation Cron Job Script
 * 
 * This script should be run via cron job to automatically check water quality
 * sensor readings and trigger appropriate automation actions.
 * 
 * Recommended cron schedule: every 5 minutes
 * 
 * Usage: php /path/to/automation_cron.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/automation_cron.log');

// Include required files
require_once __DIR__ . '/../config/database.php';

// Define water quality thresholds (same as in automation_control.php)
define('TDS_CRITICAL_MIN', 200);  // ppm - Critical range starts at 200
define('TDS_CRITICAL_MAX', 500);  // ppm - Critical range ends at 500
define('TDS_MEDIUM_MIN', 150);    // ppm - Medium range starts at 150
define('TDS_MEDIUM_MAX', 200);    // ppm - Medium range ends at 200

define('TURBIDITY_CRITICAL_MIN', 10.0);  // NTU - Critical range starts at 10.0 (EPA limit)
define('TURBIDITY_CRITICAL_MAX', 50.0);  // NTU - Critical range ends at 50.0
define('TURBIDITY_MEDIUM_MIN', 5.0);     // NTU - Medium range starts at 5.0
define('TURBIDITY_MEDIUM_MAX', 10.0);    // NTU - Medium range ends at 10.0

// Log function
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    // Write to log file
    $logFile = __DIR__ . '/../logs/automation_cron.log';
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    
    // Also output to console if running from command line
    if (php_sapi_name() === 'cli') {
        echo $logMessage;
    }
}

try {
    logMessage("Starting automation cron job");
    
    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check if automation is enabled
    $automation_result = $conn->query("SELECT enabled, filter_auto_enabled FROM automation_settings WHERE id = 1");
    $automation_settings = $automation_result->fetch_assoc();
    
    if (!$automation_settings) {
        logMessage("No automation settings found, creating default settings", "WARNING");
        $conn->query("INSERT INTO automation_settings (id, enabled, filter_auto_enabled, last_check, created_at) VALUES (1, 1, 1, NOW(), NOW())");
        $automation_settings = ['enabled' => 1, 'filter_auto_enabled' => 1];
    }
    
    if (!$automation_settings['enabled']) {
        logMessage("Automation is disabled, skipping check");
        exit(0);
    }
    
    if (!$automation_settings['filter_auto_enabled']) {
        logMessage("Filter automation is disabled, skipping check");
        exit(0);
    }
    
    // Get latest sensor readings
    $result = $conn->query("SELECT turbidity, tds, ph, temperature, reading_time FROM water_readings ORDER BY reading_time DESC LIMIT 1");
    $latest_reading = $result->fetch_assoc();
    
    if (!$latest_reading) {
        logMessage("No sensor data available", "WARNING");
        exit(1);
    }
    
    logMessage("Latest reading - TDS: {$latest_reading['tds']} ppm, Turbidity: {$latest_reading['turbidity']} NTU, Time: {$latest_reading['reading_time']}");
    
    // Get thresholds from database
    $thresholds_result = $conn->query("SELECT 
        tds_critical_min, tds_critical_max, tds_medium_min, tds_medium_max,
        turbidity_critical_min, turbidity_critical_max, turbidity_medium_min, turbidity_medium_max
        FROM automation_settings WHERE id = 1");
    $thresholds = $thresholds_result->fetch_assoc();
    
    if (!$thresholds) {
        logMessage("No automation thresholds found, using default constants", "WARNING");
        $tds_critical_min = TDS_CRITICAL_MIN;
        $tds_critical_max = TDS_CRITICAL_MAX;
        $tds_medium_min = TDS_MEDIUM_MIN;
        $tds_medium_max = TDS_MEDIUM_MAX;
        $turbidity_critical_min = TURBIDITY_CRITICAL_MIN;
        $turbidity_critical_max = TURBIDITY_CRITICAL_MAX;
        $turbidity_medium_min = TURBIDITY_MEDIUM_MIN;
        $turbidity_medium_max = TURBIDITY_MEDIUM_MAX;
    } else {
        $tds_critical_min = $thresholds['tds_critical_min'];
        $tds_critical_max = $thresholds['tds_critical_max'];
        $tds_medium_min = $thresholds['tds_medium_min'];
        $tds_medium_max = $thresholds['tds_medium_max'];
        $turbidity_critical_min = $thresholds['turbidity_critical_min'];
        $turbidity_critical_max = $thresholds['turbidity_critical_max'];
        $turbidity_medium_min = $thresholds['turbidity_medium_min'];
        $turbidity_medium_max = $thresholds['turbidity_medium_max'];
    }
    
    // Analyze sensor data
    $tds = $latest_reading['tds'];
    $turbidity = $latest_reading['turbidity'];
    
    // Determine TDS status
    $tds_status = 'normal';
    if ($tds >= $tds_critical_min && $tds <= $tds_critical_max) {
        $tds_status = 'critical';
    } elseif ($tds >= $tds_medium_min && $tds <= $tds_medium_max) {
        $tds_status = 'medium';
    }
    
    // Determine turbidity status
    $turbidity_status = 'normal';
    if ($turbidity >= $turbidity_critical_min && $turbidity <= $turbidity_critical_max) {
        $turbidity_status = 'critical';
    } elseif ($turbidity >= $turbidity_medium_min && $turbidity <= $turbidity_medium_max) {
        $turbidity_status = 'medium';
    }
    
    logMessage("Analysis - TDS: {$tds_status}, Turbidity: {$turbidity_status}");
    
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
    
    // Get current relay state for filter (relay 1)
    $relay_result = $conn->query("SELECT state FROM relay_states WHERE relay_number = 1");
    $current_filter_state = $relay_result->fetch_assoc();
    $filter_currently_on = $current_filter_state && $current_filter_state['state'] == 1;
    
    logMessage("Current filter state: " . ($filter_currently_on ? 'ON' : 'OFF'));
    logMessage("Should activate filter: " . ($should_activate_filter ? 'YES' : 'NO'));
    
    // Determine action based on analysis
    $action_taken = "none";
    
    if ($should_activate_filter && !$filter_currently_on) {
        // Activate filter
        $stmt = $conn->prepare("UPDATE relay_states SET state = 1 WHERE relay_number = 1");
        $stmt->execute();
        $action_taken = "filter_activated";
        
        logMessage("Filter activated automatically: {$reason}", "ACTION");
        
        // Log the automation action
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, performed_by, message, details, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
        $system_user_id = 0; // System user
        $action_type = "automation_triggered";
        $performed_by = "Automation System";
        $message = "Filter activated due to water quality";
        $details = $reason;
        $stmt->bind_param("issss", $system_user_id, $action_type, $performed_by, $message, $details);
        $stmt->execute();
        
    } elseif (!$should_activate_filter && $filter_currently_on) {
        // Deactivate filter if conditions are now normal
        $stmt = $conn->prepare("UPDATE relay_states SET state = 0 WHERE relay_number = 1");
        $stmt->execute();
        $action_taken = "filter_deactivated";
        
        logMessage("Filter deactivated automatically - water quality normal", "ACTION");
        
        // Log the automation action
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, performed_by, message, details, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
        $system_user_id = 0; // System user
        $action_type = "automation_triggered";
        $performed_by = "Automation System";
        $message = "Filter deactivated - water quality normal";
        $details = "TDS: {$tds} ppm ({$tds_status}), Turbidity: {$turbidity} NTU ({$turbidity_status})";
        $stmt->bind_param("issss", $system_user_id, $action_type, $performed_by, $message, $details);
        $stmt->execute();
    }
    
    // Update last check time
    $conn->query("UPDATE automation_settings SET last_check = NOW() WHERE id = 1");
    
    logMessage("Automation check completed - Action taken: {$action_taken}");
    
    // Exit with success
    exit(0);
    
} catch (Exception $e) {
    logMessage("Error in automation cron job: " . $e->getMessage(), "ERROR");
    exit(1);
}
?> 