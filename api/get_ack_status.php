<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once '../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Ensure table exists; if not, return empty status gracefully
    $check = $conn->query("SHOW TABLES LIKE 'sensor_acknowledgments'");
    if ($check->num_rows == 0) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    // Remove expired acknowledgments proactively
    $conn->query("DELETE FROM sensor_acknowledgments WHERE acknowledged_until < NOW()");

    $res = $conn->query("SELECT sensor_type, acknowledged_until, acknowledged_at, last_action, last_person FROM sensor_acknowledgments");
    $data = [];
    while ($row = $res->fetch_assoc()) {
        $data[$row['sensor_type']] = [
            'acknowledged_until' => $row['acknowledged_until'],
            'acknowledged_at' => $row['acknowledged_at'],
            'last_action' => $row['last_action'],
            'last_person' => $row['last_person']
        ];
    }
    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>


