<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $requiredFields = ['alert_type', 'alert_message', 'action_taken', 'details'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Sanitize input data
    $alertType = htmlspecialchars(trim($input['alert_type']));
    $alertMessage = htmlspecialchars(trim($input['alert_message']));
    $actionTaken = htmlspecialchars(trim($input['action_taken']));
    $details = htmlspecialchars(trim($input['details']));
    $responsiblePerson = isset($input['responsible_person']) ? htmlspecialchars(trim($input['responsible_person'])) : '';
    $timestamp = isset($input['timestamp']) ? $input['timestamp'] : date('Y-m-d H:i:s');
    $values = isset($input['values']) ? $input['values'] : [];
    
    // Validate alert type
    if (!in_array($alertType, ['turbidity', 'tds', 'ph'])) {
        throw new Exception('Invalid alert type');
    }
    
    // Validate action taken
    $validActions = [
        'investigated', 'corrected', 'monitoring', 'maintenance', 'reported', 'other',
        'filter_replacement', 'system_maintenance', 'chemical_treatment', 
        'system_flush', 'investigation', 'manual_intervention'
    ];
    if (!in_array($actionTaken, $validActions)) {
        throw new Exception('Invalid action taken: ' . $actionTaken);
    }
    
    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check if alert_acknowledgments table exists, create if not
    $checkTable = $conn->query("SHOW TABLES LIKE 'alert_acknowledgments'");
    if ($checkTable->num_rows == 0) {
        $createTable = "
            CREATE TABLE alert_acknowledgments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                alert_type ENUM('turbidity', 'tds', 'ph') NOT NULL,
                alert_message TEXT NOT NULL,
                action_taken VARCHAR(50) NOT NULL,
                details TEXT NOT NULL,
                responsible_person VARCHAR(100),
                sensor_values JSON,
                acknowledged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                alert_timestamp TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_alert_type (alert_type),
                INDEX idx_acknowledged_at (acknowledged_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        if (!$conn->query($createTable)) {
            throw new Exception('Failed to create acknowledgments table: ' . $conn->error);
        }
    }
    
    // Prepare sensor values as JSON
    $sensorValuesJson = json_encode($values);
    
    // Insert acknowledgment record
    $stmt = $conn->prepare("
        INSERT INTO alert_acknowledgments 
        (alert_type, alert_message, action_taken, details, responsible_person, sensor_values, alert_timestamp) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param("sssssss", 
        $alertType, 
        $alertMessage, 
        $actionTaken, 
        $details, 
        $responsiblePerson, 
        $sensorValuesJson, 
        $timestamp
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to insert acknowledgment: ' . $stmt->error);
    }
    
    $acknowledgmentId = $conn->insert_id;
    
    // Ensure per-sensor acknowledgment status table exists and upsert 5h window
    $checkTable2 = $conn->query("SHOW TABLES LIKE 'sensor_acknowledgments'");
    if ($checkTable2->num_rows == 0) {
        $create2 = "
            CREATE TABLE sensor_acknowledgments (
                sensor_type ENUM('turbidity','tds','ph') PRIMARY KEY,
                acknowledged_until DATETIME NOT NULL,
                acknowledged_at DATETIME NOT NULL,
                last_action VARCHAR(50) NULL,
                last_person VARCHAR(100) NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_until (acknowledged_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        if (!$conn->query($create2)) {
            throw new Exception('Failed to create sensor_acknowledgments table: ' . $conn->error);
        }
    }

    $ackAt = date('Y-m-d H:i:s');
    $ackUntil = date('Y-m-d H:i:s', time() + 5 * 60 * 60); // 5 hours
    $upsert = $conn->prepare("INSERT INTO sensor_acknowledgments (sensor_type, acknowledged_until, acknowledged_at, last_action, last_person)
                              VALUES (?, ?, ?, ?, ?)
                              ON DUPLICATE KEY UPDATE acknowledged_until = VALUES(acknowledged_until), acknowledged_at = VALUES(acknowledged_at), last_action = VALUES(last_action), last_person = VALUES(last_person)");
    if ($upsert) {
        $upsert->bind_param('sssss', $alertType, $ackUntil, $ackAt, $actionTaken, $responsiblePerson);
        $upsert->execute();
        $upsert->close();
    }

    // Log the acknowledgment for audit purposes
    error_log("Alert acknowledged - ID: $acknowledgmentId, Type: $alertType, Action: $actionTaken, Person: $responsiblePerson");
    
    // Update SSE marker so all clients receive a push
    try {
        $markerDir = __DIR__ . '/logs';
        if (!is_dir($markerDir)) { @mkdir($markerDir, 0775, true); }
        $marker = [
            't' => time(),
            'alert_type' => $alertType,
            'acknowledged_at' => date('c')
        ];
        @file_put_contents($markerDir . '/last_ack.json', json_encode($marker));
        @chmod($markerDir . '/last_ack.json', 0664);
    } catch (\Throwable $e) { /* best-effort */ }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Alert acknowledged successfully',
        'acknowledgment_id' => $acknowledgmentId,
        'data' => [
            'alert_type' => $alertType,
            'action_taken' => $actionTaken,
            'acknowledged_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Acknowledgment error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
