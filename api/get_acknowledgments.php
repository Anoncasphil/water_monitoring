<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Get query parameters
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50; // Max 100 records
    $offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;
    $alertType = isset($_GET['type']) ? $_GET['type'] : null;
    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : null;
    $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : null;
    
    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'alert_acknowledgments'");
    if ($checkTable->num_rows == 0) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'total' => 0,
            'message' => 'No acknowledgments table found'
        ]);
        exit();
    }
    
    // Build WHERE clause
    $whereConditions = [];
    $params = [];
    $paramTypes = '';
    
    if ($alertType && in_array($alertType, ['turbidity', 'tds'])) {
        $whereConditions[] = "alert_type = ?";
        $params[] = $alertType;
        $paramTypes .= 's';
    }
    
    if ($dateFrom) {
        $whereConditions[] = "acknowledged_at >= ?";
        $params[] = $dateFrom;
        $paramTypes .= 's';
    }
    
    if ($dateTo) {
        $whereConditions[] = "acknowledged_at <= ?";
        $params[] = $dateTo;
        $paramTypes .= 's';
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM alert_acknowledgments $whereClause";
    if (!empty($params)) {
        $countStmt = $conn->prepare($countQuery);
        $countStmt->bind_param($paramTypes, ...$params);
        $countStmt->execute();
        $totalResult = $countStmt->get_result();
        $total = $totalResult->fetch_assoc()['total'];
    } else {
        $totalResult = $conn->query($countQuery);
        $total = $totalResult->fetch_assoc()['total'];
    }
    
    // Get acknowledgments with pagination
    $query = "
        SELECT 
            id,
            alert_type,
            alert_message,
            action_taken,
            details,
            responsible_person,
            sensor_values,
            acknowledged_at,
            alert_timestamp,
            created_at
        FROM alert_acknowledgments 
        $whereClause
        ORDER BY acknowledged_at DESC 
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    $paramTypes .= 'ii';
    
    $stmt = $conn->prepare($query);
    if (!empty($paramTypes)) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $acknowledgments = [];
    while ($row = $result->fetch_assoc()) {
        // Parse sensor values JSON
        $sensorValues = null;
        if ($row['sensor_values']) {
            $sensorValues = json_decode($row['sensor_values'], true);
        }
        
        $acknowledgments[] = [
            'id' => (int)$row['id'],
            'alert_type' => $row['alert_type'],
            'alert_message' => $row['alert_message'],
            'action_taken' => $row['action_taken'],
            'details' => $row['details'],
            'responsible_person' => $row['responsible_person'],
            'sensor_values' => $sensorValues,
            'acknowledged_at' => $row['acknowledged_at'],
            'alert_timestamp' => $row['alert_timestamp'],
            'created_at' => $row['created_at']
        ];
    }
    
    // Get statistics
    $statsQuery = "
        SELECT 
            alert_type,
            COUNT(*) as count,
            action_taken,
            COUNT(*) as action_count
        FROM alert_acknowledgments 
        WHERE acknowledged_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY alert_type, action_taken
        ORDER BY alert_type, action_count DESC
    ";
    
    $statsResult = $conn->query($statsQuery);
    $statistics = [];
    while ($stat = $statsResult->fetch_assoc()) {
        if (!isset($statistics[$stat['alert_type']])) {
            $statistics[$stat['alert_type']] = [
                'total_count' => 0,
                'actions' => []
            ];
        }
        $statistics[$stat['alert_type']]['total_count'] += $stat['count'];
        $statistics[$stat['alert_type']]['actions'][] = [
            'action' => $stat['action_taken'],
            'count' => (int)$stat['action_count']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $acknowledgments,
        'pagination' => [
            'total' => (int)$total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total
        ],
        'statistics' => $statistics
    ]);
    
} catch (Exception $e) {
    error_log("Get acknowledgments error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
