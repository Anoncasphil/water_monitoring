<?php
// Enforce strict JSON output and prevent caching/HTML leakage
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
}
// Clear any active output buffers to avoid stray output before JSON
while (ob_get_level() > 0) { ob_end_clean(); }

require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Handle GET request for checking relay states (optionally for a specific relay)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $states = [];
        if (isset($_GET['relay'])) {
            $relay = (int)$_GET['relay'];
            if ($relay >= 1 && $relay <= 4) {
                $stmt = $conn->prepare("SELECT relay_number, state, manual_override FROM relay_states WHERE relay_number = ? LIMIT 1");
                $stmt->bind_param("i", $relay);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $states[] = [
                        "relay_number" => (int)$row['relay_number'],
                        "state" => (int)$row['state'],
                        "manual_override" => (int)$row['manual_override']
                    ];
                }
                $stmt->close();
            }
        } else {
            $result = $conn->query("SELECT relay_number, state, manual_override FROM relay_states ORDER BY relay_number");
            while ($row = $result->fetch_assoc()) {
                $states[] = [
                    "relay_number" => (int)$row['relay_number'],
                    "state" => (int)$row['state'],
                    "manual_override" => (int)$row['manual_override']
                ];
            }
        }

        echo json_encode([
            "success" => true,
            "states" => $states,
            // compatibility helper for clients expecting singular keys
            "relay" => isset($states[0]) ? $states[0]["relay_number"] : null,
            "state" => isset($states[0]) ? $states[0]["state"] : null
        ]);
        exit;
    }

    // Handle POST request for updating relay state
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $relay = isset($_POST['relay']) ? (int)$_POST['relay'] : null;
        $state = isset($_POST['state']) ? (int)$_POST['state'] : null;

        if ($relay === null || $state === null || $relay < 1 || $relay > 4 || ($state !== 0 && $state !== 1)) {
            throw new Exception("Invalid relay or state value");
        }

        // Update relay state in database and set manual override flag
        // Note: Arduino uses inverted logic (LOW=ON, HIGH=OFF) but database stores 1=ON, 0=OFF
        // manual_override = 1 when relay is manually turned ON, 0 when manually turned OFF
        $manual_override = $state; // 1 for ON (manual control), 0 for OFF (automation control)
        $stmt = $conn->prepare("UPDATE relay_states SET state = ?, manual_override = ? WHERE relay_number = ?");
        $stmt->bind_param("iii", $state, $manual_override, $relay);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update relay state");
        }

        // Only disable automation when relay is manually turned ON (manual_override = 1)
        if ($state == 1) {
            // When relay is manually turned ON, turn OFF all automation
            $automation_stmt = $conn->prepare("UPDATE automation_settings SET enabled = 0, filter_auto_enabled = 0, updated_at = NOW() WHERE id = 1");
            $automation_stmt->execute();
            
            // Log that automation was disabled due to manual control
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, performed_by, message, details, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
            $system_user_id = null;
            $action_type = "automation_disabled";
            $performed_by = "Manual Relay Control";
            $message = "All automation disabled due to manual relay control";
            $details = "Relay {$relay} manually turned ON - automation system disabled for safety";
            $log_stmt->bind_param("issss", $system_user_id, $action_type, $performed_by, $message, $details);
            $log_stmt->execute();
        } else {
            // When relay is manually turned OFF, check if any other relays are still manually controlled
            $check_stmt = $conn->prepare("SELECT COUNT(*) as manual_count FROM relay_states WHERE manual_override = 1");
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $manual_count = $result->fetch_assoc()['manual_count'];
            
            // If no relays are manually controlled, re-enable automation
            if ($manual_count == 0) {
                $automation_stmt = $conn->prepare("UPDATE automation_settings SET enabled = 1, filter_auto_enabled = 1, updated_at = NOW() WHERE id = 1");
                $automation_stmt->execute();
                
                // Log that automation was re-enabled
                $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, performed_by, message, details, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
                $system_user_id = null;
                $action_type = "automation_enabled";
                $performed_by = "Manual Relay Control";
                $message = "Automation re-enabled";
                $details = "Relay {$relay} manually turned OFF - no manual control active, automation re-enabled";
                $log_stmt->bind_param("issss", $system_user_id, $action_type, $performed_by, $message, $details);
                $log_stmt->execute();
            }
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
            "message" => "Relay state updated successfully",
            "states" => $states
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage()
    ]);
}
// Intentionally no closing PHP tag to avoid accidental output