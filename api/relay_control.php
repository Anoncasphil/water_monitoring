<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Handle GET request for checking relay states
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $result = $conn->query("SELECT relay_number, state, manual_override FROM relay_states ORDER BY relay_number");
        $states = [];
        
        while ($row = $result->fetch_assoc()) {
            $states[] = [
                "relay_number" => (int)$row['relay_number'],
                "state" => (int)$row['state'],
                "manual_override" => (int)$row['manual_override']
            ];
        }
        
        echo json_encode(["states" => $states]);
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
        $stmt = $conn->prepare("UPDATE relay_states SET state = ?, manual_override = 1 WHERE relay_number = ?");
        $stmt->bind_param("ii", $state, $relay);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update relay state");
        }

        // When any relay is manually controlled, turn OFF all automation
        $automation_stmt = $conn->prepare("UPDATE automation_settings SET enabled = 0, filter_auto_enabled = 0, updated_at = NOW() WHERE id = 1");
        $automation_stmt->execute();
        
        // Log that automation was disabled due to manual control
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, performed_by, message, details, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
        $system_user_id = null;
        $action_type = "automation_disabled";
        $performed_by = "Manual Relay Control";
        $message = "All automation disabled due to manual relay control";
        $details = "Relay {$relay} manually set to " . ($state ? 'ON' : 'OFF') . " - automation system disabled for safety";
        $log_stmt->bind_param("issss", $system_user_id, $action_type, $performed_by, $message, $details);
        $log_stmt->execute();

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
?> 