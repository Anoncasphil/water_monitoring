<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

try {
    $conn = Database::getInstance()->getConnection();
    
    // Function to get a setting from system_settings table
    function getSetting($conn, $name, $default = '0') {
        $stmt = $conn->prepare("SELECT value FROM system_settings WHERE name = ?");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['value'];
        }
        return $default;
    }
    
    // Function to parse range string (e.g., "2-5" to [2, 5])
    function parseRange($range) {
        $parts = explode('-', $range);
        if (count($parts) === 2) {
            return [(float)$parts[0], (float)$parts[1]];
        }
        return [0, 100]; // Default fallback
    }
    
    // Function to generate random value within range
    function randomInRange($min, $max, $decimals = 2) {
        $multiplier = pow(10, $decimals);
        return round(($min + ($max - $min) * (mt_rand() / mt_getrandmax())) * $multiplier) / $multiplier;
    }
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    if ($action === 'update_latest') {
        // Check if manipulation is enabled and running
        $manipulationEnabled = getSetting($conn, 'manipulation_enabled', '0') === '1';
        $manipulationRunning = getSetting($conn, 'manipulation_running', '0') === '1';
        
        if (!$manipulationEnabled || !$manipulationRunning) {
            echo json_encode([
                'success' => false,
                'message' => 'Manipulation not enabled or not running'
            ]);
            exit;
        }
        
        // Get the latest reading ID
        $stmt = $conn->prepare("SELECT id, turbidity, tds, ph, temperature, `in`, reading_time FROM water_readings ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result || $result->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'No readings found in database'
            ]);
            exit;
        }
        
        $latestReading = $result->fetch_assoc();
        $stmt->close();
        
        // Get manipulation settings for each sensor
        $manipulatePh = getSetting($conn, 'manipulate_ph', '0') === '1';
        $manipulateTurbidity = getSetting($conn, 'manipulate_turbidity', '0') === '1';
        $manipulateTds = getSetting($conn, 'manipulate_tds', '0') === '1';
        $manipulateTemperature = getSetting($conn, 'manipulate_temperature', '0') === '1';
        
        // Check for override values from JavaScript (these take priority)
        $phOverride = isset($_POST['ph_override']) && $_POST['ph_override'] !== '' ? (float)$_POST['ph_override'] : null;
        $turbidityOverride = isset($_POST['turbidity_override']) && $_POST['turbidity_override'] !== '' ? (float)$_POST['turbidity_override'] : null;
        $tdsOverride = isset($_POST['tds_override']) && $_POST['tds_override'] !== '' ? (float)$_POST['tds_override'] : null;
        $temperatureOverride = isset($_POST['temperature_override']) && $_POST['temperature_override'] !== '' ? (float)$_POST['temperature_override'] : null;
        
        // Debug: Log override values received
        error_log("OVERRIDE VALUES RECEIVED:");
        error_log("  pH override: " . ($phOverride !== null ? $phOverride : 'NOT SET'));
        error_log("  Turbidity override: " . ($turbidityOverride !== null ? $turbidityOverride : 'NOT SET'));
        error_log("  TDS override: " . ($tdsOverride !== null ? $tdsOverride : 'NOT SET'));
        error_log("  Temperature override: " . ($temperatureOverride !== null ? $temperatureOverride : 'NOT SET'));
        
        // Prepare values for update - only change what's actually enabled for manipulation
        $newTurbidity = $latestReading['turbidity']; // Keep original if not manipulated
        $newTds = $latestReading['tds']; // Keep original if not manipulated
        $newPh = $latestReading['ph']; // Keep original if not manipulated
        $newTemperature = $latestReading['temperature']; // Keep original if not manipulated
        
        // Track which sensors were actually manipulated
        $manipulatedSensors = [];
        
        // Apply manipulation ONLY for sensors that are checked/enabled
        // Use override values if provided, otherwise generate random values
        if ($manipulateTurbidity) {
            if ($turbidityOverride !== null) {
                $newTurbidity = $turbidityOverride;
                error_log("Turbidity overridden to: $newTurbidity");
            } else {
                $turbidityRange = getSetting($conn, 'turbidity_range', '1-2');
                [$turMin, $turMax] = parseRange($turbidityRange);
                $newTurbidity = randomInRange($turMin, $turMax, 2);
                error_log("Turbidity randomly generated from range $turbidityRange to: $newTurbidity");
            }
            $manipulatedSensors[] = 'turbidity';
        }
        
        if ($manipulateTds) {
            if ($tdsOverride !== null) {
                $newTds = $tdsOverride;
                error_log("TDS overridden to: $newTds");
            } else {
                $tdsRange = getSetting($conn, 'tds_range', '0-50');
                [$tdsMin, $tdsMax] = parseRange($tdsRange);
                $newTds = randomInRange($tdsMin, $tdsMax, 2);
                error_log("TDS randomly generated from range $tdsRange to: $newTds");
            }
            $manipulatedSensors[] = 'tds';
        }
        
        if ($manipulatePh) {
            if ($phOverride !== null) {
                $newPh = $phOverride;
                error_log("pH overridden to: $newPh");
            } else {
                $phRange = getSetting($conn, 'ph_range', '6-7');
                [$phMin, $phMax] = parseRange($phRange);
                $newPh = randomInRange($phMin, $phMax, 2);
                error_log("pH randomly generated from range $phRange to: $newPh");
            }
            $manipulatedSensors[] = 'ph';
        }
        
        if ($manipulateTemperature) {
            if ($temperatureOverride !== null) {
                $newTemperature = $temperatureOverride;
                error_log("Temperature overridden to: $newTemperature");
            } else {
                $temperatureRange = getSetting($conn, 'temperature_range', '20-25');
                [$tempMin, $tempMax] = parseRange($temperatureRange);
                $newTemperature = randomInRange($tempMin, $tempMax, 2);
            }
            $manipulatedSensors[] = 'temperature';
        }
        
        // Log which sensors were actually manipulated
        if (empty($manipulatedSensors)) {
            error_log("No sensors were manipulated - all manipulation checkboxes are unchecked");
        } else {
            error_log("Manipulated sensors: " . implode(', ', $manipulatedSensors));
        }
        
        // Only update if there are actual changes to make
        $hasChanges = ($newTurbidity != $latestReading['turbidity']) || 
                     ($newTds != $latestReading['tds']) || 
                     ($newPh != $latestReading['ph']) || 
                     ($newTemperature != $latestReading['temperature']);
        
        if (!$hasChanges) {
            echo json_encode([
                'success' => true,
                'message' => 'No changes needed - all values are already as expected',
                'data' => [
                    'id' => $latestReading['id'],
                    'original' => [
                        'turbidity' => $latestReading['turbidity'],
                        'tds' => $latestReading['tds'],
                        'ph' => $latestReading['ph'],
                        'temperature' => $latestReading['temperature']
                    ],
                    'updated' => [
                        'turbidity' => $newTurbidity,
                        'tds' => $newTds,
                        'ph' => $newPh,
                        'temperature' => $newTemperature
                    ],
                    'manipulated_sensors' => [
                        'turbidity' => $manipulateTurbidity,
                        'tds' => $manipulateTds,
                        'ph' => $manipulatePh,
                        'temperature' => $manipulateTemperature
                    ],
                    'changes_made' => false
                ]
            ]);
            exit;
        }
        
        // Update the latest reading
        $updateStmt = $conn->prepare("UPDATE water_readings SET turbidity = ?, tds = ?, ph = ?, temperature = ? WHERE id = ?");
        $updateStmt->bind_param('ddddi', $newTurbidity, $newTds, $newPh, $newTemperature, $latestReading['id']);
        $updateOk = $updateStmt->execute();
        $updateStmt->close();
        
        if ($updateOk) {
            // Log the update for debugging
            error_log("UPDATED LATEST READING - ID: {$latestReading['id']}");
            error_log("  Original - T: {$latestReading['turbidity']}, TDS: {$latestReading['tds']}, pH: {$latestReading['ph']}, Temp: {$latestReading['temperature']}");
            error_log("  Updated  - T: $newTurbidity, TDS: $newTds, pH: $newPh, Temp: $newTemperature");
            
            echo json_encode([
                'success' => true,
                'message' => 'Latest reading updated successfully',
                'data' => [
                    'id' => $latestReading['id'],
                    'original' => [
                        'turbidity' => $latestReading['turbidity'],
                        'tds' => $latestReading['tds'],
                        'ph' => $latestReading['ph'],
                        'temperature' => $latestReading['temperature']
                    ],
                    'updated' => [
                        'turbidity' => $newTurbidity,
                        'tds' => $newTds,
                        'ph' => $newPh,
                        'temperature' => $newTemperature
                    ],
                    'manipulated_sensors' => [
                        'turbidity' => $manipulateTurbidity,
                        'tds' => $manipulateTds,
                        'ph' => $manipulatePh,
                        'temperature' => $manipulateTemperature
                    ],
                    'changes_made' => true,
                    'manipulated_sensors_list' => $manipulatedSensors
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update reading'
            ]);
        }
        
    } elseif ($action === 'get_latest') {
        // Get the latest reading
        $stmt = $conn->prepare("SELECT id, turbidity, tds, ph, temperature, `in`, reading_time FROM water_readings ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $reading = $result->fetch_assoc();
            echo json_encode([
                'success' => true,
                'data' => $reading
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No readings found'
            ]);
        }
        $stmt->close();
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action. Use: update_latest or get_latest'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
