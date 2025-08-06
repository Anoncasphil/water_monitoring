<?php
/**
 * Get Schedule Logs API
 * Provides schedule logs data for real-time updates
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

// Load database configuration
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get pagination parameters
    $logs_per_page = isset($_GET['logs_per_page']) ? max(10, min(100, intval($_GET['logs_per_page']))) : 20;
    $current_page = isset($_GET['logs_page']) ? max(1, intval($_GET['logs_page'])) : 1;
    $offset = ($current_page - 1) * $logs_per_page;
    
    // Get total count for pagination
    $count_result = $conn->query("SELECT COUNT(*) as total FROM schedule_logs");
    $total_logs = $count_result ? $count_result->fetch_assoc()['total'] : 0;
    $total_pages = ceil($total_logs / $logs_per_page);
    
    // Get logs with pagination
    $logs_sql = "
        SELECT sl.* 
        FROM schedule_logs sl 
        ORDER BY sl.executed_time DESC 
        LIMIT ? OFFSET ?
    ";
    $logs_stmt = $conn->prepare($logs_sql);
    $logs_stmt->bind_param("ii", $logs_per_page, $offset);
    $logs_stmt->execute();
    $logs_result = $logs_stmt->get_result();
    $schedule_logs = $logs_result ? $logs_result->fetch_all(MYSQLI_ASSOC) : [];
    $logs_stmt->close();
    
    // Prepare pagination data
    $pagination = [
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'total' => $total_logs,
        'per_page' => $logs_per_page,
        'showing_from' => $offset + 1,
        'showing_to' => min($offset + $logs_per_page, $total_logs)
    ];
    
    // Return success response
    echo json_encode([
        'success' => true,
        'logs' => $schedule_logs,
        'pagination' => $pagination
    ]);
    
} catch (Exception $e) {
    error_log("Get schedule logs error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch schedule logs',
        'logs' => [],
        'pagination' => [
            'current_page' => 1,
            'total_pages' => 0,
            'total' => 0,
            'per_page' => 20,
            'showing_from' => 0,
            'showing_to' => 0
        ]
    ]);
}
?> 