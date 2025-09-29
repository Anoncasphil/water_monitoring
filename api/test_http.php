<?php
// Simple HTTP test endpoint for Arduino
// This file tests if HTTP access is working without HTTPS redirects

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

try {
    $response = [
        "success" => true,
        "message" => "HTTP access working correctly",
        "timestamp" => date('Y-m-d H:i:s'),
        "protocol" => $_SERVER['HTTPS'] ? 'HTTPS' : 'HTTP',
        "method" => $_SERVER['REQUEST_METHOD'],
        "user_agent" => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        "server_time" => time()
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage(),
        "timestamp" => date('Y-m-d H:i:s')
    ]);
}
?>
