<?php
// API Test Script - Test all endpoints for proper HTTP/HTTPS handling
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

try {
    $response = [
        "success" => true,
        "message" => "API endpoints are accessible",
        "timestamp" => date('Y-m-d H:i:s'),
        "protocol" => $_SERVER['HTTPS'] ? 'HTTPS' : 'HTTP',
        "method" => $_SERVER['REQUEST_METHOD'],
        "server_info" => [
            "HTTP_HOST" => $_SERVER['HTTP_HOST'] ?? 'Unknown',
            "REQUEST_URI" => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            "SERVER_NAME" => $_SERVER['SERVER_NAME'] ?? 'Unknown',
            "SERVER_PORT" => $_SERVER['SERVER_PORT'] ?? 'Unknown'
        ],
        "headers" => [
            "User-Agent" => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            "Accept" => $_SERVER['HTTP_ACCEPT'] ?? 'Unknown',
            "Content-Type" => $_SERVER['CONTENT_TYPE'] ?? 'Unknown'
        ]
    ];
    
    // Test database connection
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Simple test query
        $result = $conn->query("SELECT COUNT(*) as count FROM relay_states");
        if ($result) {
            $row = $result->fetch_assoc();
            $response["database"] = [
                "status" => "connected",
                "relay_count" => $row['count']
            ];
        } else {
            $response["database"] = ["status" => "query_failed"];
        }
    } catch (Exception $e) {
        $response["database"] = [
            "status" => "error",
            "error" => $e->getMessage()
        ];
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage(),
        "timestamp" => date('Y-m-d H:i:s')
    ]);
}
?>
