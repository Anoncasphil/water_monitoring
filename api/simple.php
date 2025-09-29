<?php
// Simple endpoint that works with HTTP redirects
// This file is designed to work even with Hostinger's HTTPS enforcement

// Don't redirect - just respond with data
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// Simple response that Arduino can understand
echo json_encode([
    "status" => "ok",
    "message" => "Arduino HTTP endpoint working",
    "timestamp" => date('Y-m-d H:i:s'),
    "protocol" => isset($_SERVER['HTTPS']) ? 'HTTPS' : 'HTTP',
    "arduino_ready" => true
]);
?>
