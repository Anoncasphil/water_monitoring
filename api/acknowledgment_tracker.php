<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

try {
    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Create acknowledgment tracker table if it doesn't exist
    $createTable = "
    CREATE TABLE IF NOT EXISTS acknowledgment_tracker (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sensor_type ENUM('turbidity', 'tds', 'ph') NOT NULL,
        sensor_value DECIMAL(10,2) NOT NULL,
        alert_level ENUM('critical', 'warning') NOT NULL,
        alert_message TEXT NOT NULL,
        acknowledged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        acknowledged_by VARCHAR(100) DEFAULT 'System User',
        action_taken VARCHAR(50) DEFAULT 'investigated',
        details TEXT DEFAULT 'Acknowledged by user',
        expires_at TIMESTAMP NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_sensor_type (sensor_type),
        INDEX idx_acknowledged_at (acknowledged_at),
        INDEX idx_expires_at (expires_at),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $conn->query($createTable);
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            // Get current acknowledgment status
            $query = "
                SELECT 
                    sensor_type,
                    sensor_value,
                    alert_level,
                    alert_message,
                    acknowledged_at,
                    acknowledged_by,
                    action_taken,
                    details,
                    expires_at,
                    TIMESTAMPDIFF(MINUTE, acknowledged_at, NOW()) as minutes_acknowledged,
                    TIMESTAMPDIFF(MINUTE, NOW(), expires_at) as minutes_remaining,
                    is_active
                FROM acknowledgment_tracker 
                WHERE is_active = TRUE 
                AND expires_at > NOW()
                ORDER BY acknowledged_at DESC
            ";
            
            $result = $conn->query($query);
            $acknowledgments = [];
            
            while ($row = $result->fetch_assoc()) {
                $acknowledgments[] = [
                    'id' => (int)$row['id'],
                    'sensor_type' => $row['sensor_type'],
                    'sensor_value' => (float)$row['sensor_value'],
                    'alert_level' => $row['alert_level'],
                    'alert_message' => $row['alert_message'],
                    'acknowledged_at' => $row['acknowledged_at'],
                    'acknowledged_by' => $row['acknowledged_by'],
                    'action_taken' => $row['action_taken'],
                    'details' => $row['details'],
                    'expires_at' => $row['expires_at'],
                    'minutes_acknowledged' => (int)$row['minutes_acknowledged'],
                    'minutes_remaining' => (int)$row['minutes_remaining'],
                    'is_active' => (bool)$row['is_active']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'data' => $acknowledgments
            ]);
            break;
            
        case 'POST':
            // Add new acknowledgment
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                throw new Exception('Invalid JSON input');
            }
            
            $requiredFields = ['sensor_type', 'sensor_value', 'alert_level', 'alert_message'];
            foreach ($requiredFields as $field) {
                if (!isset($input[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }
            
            $sensorType = htmlspecialchars(trim($input['sensor_type']));
            $sensorValue = (float)$input['sensor_value'];
            $alertLevel = htmlspecialchars(trim($input['alert_level']));
            $alertMessage = htmlspecialchars(trim($input['alert_message']));
            $acknowledgedBy = isset($input['acknowledged_by']) ? htmlspecialchars(trim($input['acknowledged_by'])) : 'System User';
            $actionTaken = isset($input['action_taken']) ? htmlspecialchars(trim($input['action_taken'])) : 'investigated';
            $details = isset($input['details']) ? htmlspecialchars(trim($input['details'])) : 'Acknowledged by user';
            
            // Validate sensor type
            if (!in_array($sensorType, ['turbidity', 'tds', 'ph'])) {
                throw new Exception('Invalid sensor type');
            }
            
            // Validate alert level
            if (!in_array($alertLevel, ['critical', 'warning'])) {
                throw new Exception('Invalid alert level');
            }
            
            // Calculate expiration time (5 hours from now)
            $expiresAt = date('Y-m-d H:i:s', strtotime('+5 hours'));
            
            // First, deactivate any existing active acknowledgments for this sensor
            $deactivateQuery = "UPDATE acknowledgment_tracker SET is_active = FALSE WHERE sensor_type = ? AND is_active = TRUE";
            $deactivateStmt = $conn->prepare($deactivateQuery);
            $deactivateStmt->bind_param('s', $sensorType);
            $deactivateStmt->execute();
            $deactivateStmt->close();
            
            // Insert new acknowledgment
            $insertQuery = "
                INSERT INTO acknowledgment_tracker 
                (sensor_type, sensor_value, alert_level, alert_message, acknowledged_by, action_taken, details, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param('sdssssss', $sensorType, $sensorValue, $alertLevel, $alertMessage, $acknowledgedBy, $actionTaken, $details, $expiresAt);
            
            if ($stmt->execute()) {
                $newId = $conn->insert_id;
                echo json_encode([
                    'success' => true,
                    'message' => 'Acknowledgment recorded successfully',
                    'data' => ['id' => $newId]
                ]);
            } else {
                throw new Exception('Failed to insert acknowledgment');
            }
            
            $stmt->close();
            break;
            
        case 'PUT':
            // Update acknowledgment status
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['sensor_type'])) {
                throw new Exception('Invalid input');
            }
            
            $sensorType = htmlspecialchars(trim($input['sensor_type']));
            
            // Deactivate acknowledgment for this sensor
            $updateQuery = "UPDATE acknowledgment_tracker SET is_active = FALSE WHERE sensor_type = ? AND is_active = TRUE";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param('s', $sensorType);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Acknowledgment deactivated successfully'
                ]);
            } else {
                throw new Exception('Failed to update acknowledgment');
            }
            
            $stmt->close();
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Acknowledgment tracker error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
