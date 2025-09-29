<?php
// Simple HTTP test endpoint specifically for Arduino Uno R4 WiFi
// This endpoint forces HTTP access and provides detailed connection info

// Force HTTP access
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    // If accessed via HTTPS, redirect to HTTP
    $http_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('Location: ' . $http_url, true, 302);
    exit();
}

// Set headers for JSON response
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

try {
    $response = [
        "success" => true,
        "message" => "Arduino HTTP access working correctly",
        "timestamp" => date('Y-m-d H:i:s'),
        "protocol" => $_SERVER['HTTPS'] ? 'HTTPS' : 'HTTP',
        "method" => $_SERVER['REQUEST_METHOD'],
        "server_info" => [
            "HTTP_HOST" => $_SERVER['HTTP_HOST'] ?? 'Unknown',
            "REQUEST_URI" => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            "SERVER_NAME" => $_SERVER['SERVER_NAME'] ?? 'Unknown',
            "SERVER_PORT" => $_SERVER['SERVER_PORT'] ?? 'Unknown',
            "HTTPS" => $_SERVER['HTTPS'] ?? 'off'
        ],
        "client_info" => [
            "User-Agent" => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            "Remote_Addr" => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            "Accept" => $_SERVER['HTTP_ACCEPT'] ?? 'Unknown'
        ],
        "arduino_compatible" => true,
        "http_only" => true
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage(),
        "timestamp" => date('Y-m-d H:i:s'),
        "arduino_compatible" => false
    ]);
}
?>
