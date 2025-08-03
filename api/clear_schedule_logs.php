<?php
/**
 * Clear Schedule Logs API
 * This endpoint clears all execution logs from the schedule_logs table
 */

header('Content-Type: application/json');

// Set timezone
date_default_timezone_set('Asia/Manila');

// Load database configuration
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Clear all logs
    $stmt = $conn->prepare("TRUNCATE TABLE schedule_logs");
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'All schedule logs have been cleared successfully',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to clear logs: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?> 