<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

require_once '../config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['sensor_type'])) {
        throw new Exception('Missing sensor_type');
    }

    $sensor = strtolower(trim($input['sensor_type']));
    if (!in_array($sensor, ['turbidity','tds','ph'], true)) {
        throw new Exception('Invalid sensor_type');
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Ensure table exists
    $check = $conn->query("SHOW TABLES LIKE 'sensor_acknowledgments'");
    if ($check->num_rows == 0) {
        echo json_encode(['success' => true, 'message' => 'Nothing to clear']);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM sensor_acknowledgments WHERE sensor_type = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    $stmt->bind_param('s', $sensor);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    // Touch SSE marker
    try {
        $markerDir = __DIR__ . '/logs';
        if (!is_dir($markerDir)) { @mkdir($markerDir, 0775, true); }
        $marker = [ 't' => time(), 'cleared' => $sensor ];
        @file_put_contents($markerDir . '/last_ack.json', json_encode($marker));
        @chmod($markerDir . '/last_ack.json', 0664);
    } catch (\Throwable $e) { /* ignore */ }

    echo json_encode(['success' => true, 'cleared' => $sensor, 'rows' => $affected]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>


