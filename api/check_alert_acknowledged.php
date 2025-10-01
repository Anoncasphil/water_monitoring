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
    if (!isset($input['alert_type']) || !isset($input['alert_message'])) {
        throw new Exception('Missing required fields: alert_type and alert_message');
    }
    
    $alertType = htmlspecialchars(trim($input['alert_type']));
    $alertMessage = htmlspecialchars(trim($input['alert_message']));
    $alertTimestamp = isset($input['alert_timestamp']) ? $input['alert_timestamp'] : null;
    
    // Validate alert type
    if (!in_array($alertType, ['turbidity', 'tds', 'ph'])) {
        throw new Exception('Invalid alert type');
    }
    
    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'alert_acknowledgments'");
    if ($checkTable->num_rows == 0) {
        echo json_encode([
            'success' => true,
            'acknowledged' => false,
            'message' => 'No acknowledgments table found'
        ]);
        exit();
    }
    
    // Check if there's an acknowledgment for this alert type within the last 24 hours
    $query = "
        SELECT COUNT(*) as count 
        FROM alert_acknowledgments 
        WHERE alert_type = ? 
        AND acknowledged_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY acknowledged_at DESC 
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $alertType);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $acknowledged = ($row['count'] > 0);
    
    // If we have a specific timestamp, check for acknowledgments after that time
    if ($alertTimestamp && !$acknowledged) {
        $timestampQuery = "
            SELECT COUNT(*) as count 
            FROM alert_acknowledgments 
            WHERE alert_type = ? 
            AND alert_timestamp >= ?
            ORDER BY acknowledged_at DESC 
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($timestampQuery);
        $stmt->bind_param("ss", $alertType, $alertTimestamp);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $acknowledged = ($row['count'] > 0);
    }
    
    echo json_encode([
        'success' => true,
        'acknowledged' => $acknowledged,
        'alert_type' => $alertType,
        'checked_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Check acknowledgment error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'acknowledged' => false
    ]);
}
?>
