<?php
// Arduino-specific relay control endpoint that works with HTTP
// This endpoint is specifically designed for Arduino HTTP requests

// Force HTTP response headers to prevent HTTPS redirects
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    // If accessed via HTTPS, allow it but set headers to indicate HTTP is OK
    header('Strict-Transport-Security: max-age=0; includeSubDomains');
}

// Set headers to allow HTTP access
header('X-Frame-Options: SAMEORIGIN'); // Less restrictive than DENY
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, User-Agent');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include the original relay control logic
require_once 'relay_control.php';
?>
