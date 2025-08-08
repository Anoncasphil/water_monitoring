<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Define water quality thresholds
define('TDS_CRITICAL_MIN', 200);
define('TDS_CRITICAL_MAX', 500);
define('TDS_MEDIUM_MIN', 150);
define('TDS_MEDIUM_MAX', 200);

define('TURBIDITY_CRITICAL_MIN', 10.0);
define('TURBIDITY_CRITICAL_MAX', 50.0);
define('TURBIDITY_MEDIUM_MIN', 5.0);
define('TURBIDITY_MEDIUM_MAX', 10.0);

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

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
        
        // Simple analysis without database thresholds
        $tds = $latest_reading['tds'];
        $turbidity = $latest_reading['turbidity'];
        
        $tds_status = 'normal';
        if ($tds >= TDS_CRITICAL_MIN && $tds <= TDS_CRITICAL_MAX) {
            $tds_status = 'critical';
        } elseif ($tds >= TDS_MEDIUM_MIN && $tds <= TDS_MEDIUM_MAX) {
            $tds_status = 'medium';
        }
        
        $turbidity_status = 'normal';
        if ($turbidity >= TURBIDITY_CRITICAL_MIN && $turbidity <= TURBIDITY_CRITICAL_MAX) {
            $turbidity_status = 'critical';
        } elseif ($turbidity >= TURBIDITY_MEDIUM_MIN && $turbidity <= TURBIDITY_MEDIUM_MAX) {
            $turbidity_status = 'medium';
        }
        
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
        
        $analysis = [
            'tds_status' => $tds_status,
            'turbidity_status' => $turbidity_status,
            'should_activate_filter' => $should_activate_filter,
            'reason' => $reason,
            'tds_value' => $tds,
            'turbidity_value' => $turbidity
        ];
        
        echo json_encode([
            "success" => true,
            "sensor_data" => $latest_reading,
            "relay_states" => $relay_states,
            "analysis" => $analysis
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        
        if ($action === 'check_and_trigger') {
            // Get latest sensor readings
            $result = $conn->query("SELECT turbidity, tds, ph, temperature, reading_time FROM water_readings ORDER BY reading_time DESC LIMIT 1");
            $latest_reading = $result->fetch_assoc();
            
            if (!$latest_reading) {
                echo json_encode([
                    "success" => false,
                    "message" => "No sensor data available"
                ]);
                exit;
            }
            
            // Simple analysis
            $tds = $latest_reading['tds'];
            $turbidity = $latest_reading['turbidity'];
            
            $tds_status = 'normal';
            if ($tds >= TDS_CRITICAL_MIN && $tds <= TDS_CRITICAL_MAX) {
                $tds_status = 'critical';
            } elseif ($tds >= TDS_MEDIUM_MIN && $tds <= TDS_MEDIUM_MAX) {
                $tds_status = 'medium';
            }
            
            $turbidity_status = 'normal';
            if ($turbidity >= TURBIDITY_CRITICAL_MIN && $turbidity <= TURBIDITY_CRITICAL_MAX) {
                $turbidity_status = 'critical';
            } elseif ($turbidity >= TURBIDITY_MEDIUM_MIN && $turbidity <= TURBIDITY_MEDIUM_MAX) {
                $turbidity_status = 'medium';
            }
            
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
            
            // Get current relay state
            $relay_result = $conn->query("SELECT state FROM relay_states WHERE relay_number = 1");
            $current_filter_state = $relay_result->fetch_assoc();
            $filter_currently_on = $current_filter_state && $current_filter_state['state'] == 1;
            
            $action_taken = "none";
            $new_filter_state = $filter_currently_on ? 1 : 0;
            
            if ($should_activate_filter && !$filter_currently_on) {
                // Activate filter
                $stmt = $conn->prepare("UPDATE relay_states SET state = 1 WHERE relay_number = 1");
                $stmt->execute();
                $new_filter_state = 1;
                $action_taken = "filter_activated";
                
            } elseif (!$should_activate_filter && $filter_currently_on) {
                // Deactivate filter
                $stmt = $conn->prepare("UPDATE relay_states SET state = 0 WHERE relay_number = 1");
                $stmt->execute();
                $new_filter_state = 0;
                $action_taken = "filter_deactivated";
            }
            
            $analysis = [
                'tds_status' => $tds_status,
                'turbidity_status' => $turbidity_status,
                'should_activate_filter' => $should_activate_filter,
                'reason' => $reason,
                'tds_value' => $tds,
                'turbidity_value' => $turbidity
            ];
            
            echo json_encode([
                "success" => true,
                "message" => "Automation check completed",
                "analysis" => $analysis,
                "action_taken" => $action_taken,
                "filter_state" => $new_filter_state
            ]);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage()
    ]);
}
?> 