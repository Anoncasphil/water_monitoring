<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Handle GET request for checking relay states
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $result = $conn->query("SELECT relay_number, state FROM relay_states ORDER BY relay_number");
        $states = [];
        
        while ($row = $result->fetch_assoc()) {
            $states[] = [
                "relay_number" => (int)$row['relay_number'],
                "state" => (int)$row['state']
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

        // Update relay state in database
        $stmt = $conn->prepare("UPDATE relay_states SET state = ? WHERE relay_number = ?");
        $stmt->bind_param("ii", $state, $relay);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update relay state");
        }

        // Get all relay states after update
        $result = $conn->query("SELECT relay_number, state FROM relay_states ORDER BY relay_number");
        $states = [];
        
        while ($row = $result->fetch_assoc()) {
            $states[] = [
                "relay_number" => (int)$row['relay_number'],
                "state" => (int)$row['state']
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