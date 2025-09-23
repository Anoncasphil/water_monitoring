<?php
// HTTP Proxy for Arduino - bypasses HTTPS redirects
// This script accepts HTTP requests and processes them without HTTPS redirects

// Prevent any HTTPS redirects by handling the request entirely in PHP
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// Log the request for debugging
error_log("HTTP Proxy accessed: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);

try {
    // Route the request based on the 'endpoint' parameter
    $endpoint = $_GET['endpoint'] ?? $_POST['endpoint'] ?? 'relay_control';
    
    switch ($endpoint) {
        case 'relay_control':
            // Include relay control logic directly
            require_once __DIR__ . '/../config/database.php';
            
            $db = Database::getInstance();
            $conn = $db->getConnection();

            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // Get relay states
                $states = [];
                $result = $conn->query("SELECT relay_number, state, manual_override FROM relay_states ORDER BY relay_number");
                while ($row = $result->fetch_assoc()) {
                    $states[] = [
                        "relay_number" => (int)$row['relay_number'],
                        "state" => (int)$row['state'],
                        "manual_override" => (int)$row['manual_override']
                    ];
                }

                echo json_encode([
                    "success" => true,
                    "states" => $states,
                    "message" => "HTTP proxy relay states retrieved"
                ]);
                
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Update relay state
                $relay = isset($_POST['relay']) ? (int)$_POST['relay'] : null;
                $state = isset($_POST['state']) ? (int)$_POST['state'] : null;

                if ($relay === null || $state === null || $relay < 1 || $relay > 4 || ($state !== 0 && $state !== 1)) {
                    throw new Exception("Invalid relay or state value");
                }

                // Update relay state in database
                $manual_override = $state;
                $stmt = $conn->prepare("UPDATE relay_states SET state = ?, manual_override = ? WHERE relay_number = ?");
                $stmt->bind_param("iii", $state, $manual_override, $relay);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update relay state");
                }

                // Get all relay states after update
                $result = $conn->query("SELECT relay_number, state, manual_override FROM relay_states ORDER BY relay_number");
                $states = [];
                
                while ($row = $result->fetch_assoc()) {
                    $states[] = [
                        "relay_number" => (int)$row['relay_number'],
                        "state" => (int)$row['state'],
                        "manual_override" => (int)$row['manual_override']
                    ];
                }

                echo json_encode([
                    "success" => true,
                    "message" => "Relay state updated via HTTP proxy",
                    "states" => $states
                ]);
            }
            break;
            
        case 'upload':
            // Include upload logic directly
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                require_once __DIR__ . '/../config/database.php';
                
                $turbidity = $_POST['turbidity'] ?? 0;
                $tds = $_POST['tds'] ?? 0;
                $ph = $_POST['ph'] ?? 7;
                $temperature = $_POST['temperature'] ?? 25;
                
                $db = Database::getInstance();
                $conn = $db->getConnection();
                
                $stmt = $conn->prepare("INSERT INTO water_readings (turbidity, tds, ph, temperature, timestamp) VALUES (?, ?, ?, ?, NOW())");
                $stmt->bind_param("dddd", $turbidity, $tds, $ph, $temperature);
                
                if ($stmt->execute()) {
                    echo json_encode([
                        "success" => true,
                        "message" => "Data uploaded via HTTP proxy"
                    ]);
                } else {
                    throw new Exception("Failed to insert data");
                }
            }
            break;
            
        default:
            throw new Exception("Unknown endpoint: " . $endpoint);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage(),
        "proxy" => true
    ]);
}
?>
