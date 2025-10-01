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
    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'alert_acknowledgments'");
    if ($checkTable->num_rows == 0) {
        echo json_encode([
            'success' => true,
            'data' => [
                'total_acknowledged' => 0,
                'today_acknowledged' => 0,
                'this_week_acknowledged' => 0,
                'this_month_acknowledged' => 0,
                'by_type' => [
                    'turbidity' => 0,
                    'tds' => 0
                ],
                'by_action' => []
            ]
        ]);
        exit();
    }
    
    // Get total acknowledgments
    $totalQuery = "SELECT COUNT(*) as total FROM alert_acknowledgments";
    $totalResult = $conn->query($totalQuery);
    $total = $totalResult->fetch_assoc()['total'];
    
    // Get today's acknowledgments
    $todayQuery = "SELECT COUNT(*) as today FROM alert_acknowledgments WHERE DATE(acknowledged_at) = CURDATE()";
    $todayResult = $conn->query($todayQuery);
    $today = $todayResult->fetch_assoc()['today'];
    
    // Get this week's acknowledgments
    $weekQuery = "SELECT COUNT(*) as week FROM alert_acknowledgments WHERE acknowledged_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $weekResult = $conn->query($weekQuery);
    $week = $weekResult->fetch_assoc()['week'];
    
    // Get this month's acknowledgments
    $monthQuery = "SELECT COUNT(*) as month FROM alert_acknowledgments WHERE MONTH(acknowledged_at) = MONTH(NOW()) AND YEAR(acknowledged_at) = YEAR(NOW())";
    $monthResult = $conn->query($monthQuery);
    $month = $monthResult->fetch_assoc()['month'];
    
    // Get acknowledgments by type
    $typeQuery = "
        SELECT alert_type, COUNT(*) as count 
        FROM alert_acknowledgments 
        GROUP BY alert_type
    ";
    $typeResult = $conn->query($typeQuery);
    $byType = ['turbidity' => 0, 'tds' => 0, 'ph' => 0];
    while ($row = $typeResult->fetch_assoc()) {
        $byType[$row['alert_type']] = (int)$row['count'];
    }
    
    // Get acknowledgments by action
    $actionQuery = "
        SELECT action_taken, COUNT(*) as count 
        FROM alert_acknowledgments 
        GROUP BY action_taken 
        ORDER BY count DESC
    ";
    $actionResult = $conn->query($actionQuery);
    $byAction = [];
    while ($row = $actionResult->fetch_assoc()) {
        $byAction[$row['action_taken']] = (int)$row['count'];
    }
    
    // Get recent acknowledgments (last 10)
    $recentQuery = "
        SELECT 
            alert_type,
            action_taken,
            responsible_person,
            acknowledged_at,
            details
        FROM alert_acknowledgments 
        ORDER BY acknowledged_at DESC 
        LIMIT 10
    ";
    $recentResult = $conn->query($recentQuery);
    $recent = [];
    while ($row = $recentResult->fetch_assoc()) {
        $recent[] = [
            'alert_type' => $row['alert_type'],
            'action_taken' => $row['action_taken'],
            'responsible_person' => $row['responsible_person'],
            'acknowledged_at' => $row['acknowledged_at'],
            'details' => $row['details']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_acknowledged' => (int)$total,
            'today_acknowledged' => (int)$today,
            'this_week_acknowledged' => (int)$week,
            'this_month_acknowledged' => (int)$month,
            'by_type' => $byType,
            'by_action' => $byAction,
            'recent' => $recent
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get acknowledgment stats error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
