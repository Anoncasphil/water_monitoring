<?php
/**
 * Cron Trigger for Schedule Execution
 * This file can be called by external cron services to execute schedules
 * 
 * Usage: https://yourdomain.com/projtest/api/cron_trigger.php?key=your_secret_key
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

// Load database configuration
require_once __DIR__ . '/../config/database.php';

// Load environment variables
require_once __DIR__ . '/../config/EnvLoader.php';
$envLoader = new EnvLoader();
$envLoader->load(__DIR__ . '/../.env');

// Get secret key from environment
$secret_key = getenv('CRON_SECRET_KEY');

// Security: Check for secret key
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Security: Optional IP whitelist (uncomment and modify if needed)
/*
$allowed_ips = ['127.0.0.1', '::1']; // Add your cron service IPs
if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'IP not allowed']);
    exit;
}
*/

// Rate limiting (optional)
$rate_limit_file = __DIR__ . '/logs/cron_rate_limit.txt';
$current_time = time();
$rate_limit_window = 60; // 1 minute window

if (file_exists($rate_limit_file)) {
    $last_execution = (int)file_get_contents($rate_limit_file);
    if ($current_time - $last_execution < $rate_limit_window) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Rate limit exceeded']);
        exit;
    }
}

// Update rate limit
file_put_contents($rate_limit_file, $current_time);

// Include and execute the schedule system
try {
    // Set headers for JSON response
    header('Content-Type: application/json');
    
    // Capture output from execute_schedules.php
    ob_start();
    include_once __DIR__ . '/execute_schedules.php';
    $output = ob_get_clean();
    
    // Log the execution
    $log_message = date('Y-m-d H:i:s') . " - Cron trigger executed successfully\n";
    file_put_contents(__DIR__ . '/logs/cron_trigger.log', $log_message, FILE_APPEND);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Schedule execution completed',
        'timestamp' => date('Y-m-d H:i:s'),
        'output' => $output
    ]);
    
} catch (Exception $e) {
    // Log error
    $error_message = date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/logs/cron_trigger.log', $error_message, FILE_APPEND);
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Schedule execution failed',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
