<?php
/**
 * Test Cron Job for Hostinger
 * This file can be used to test if cron jobs are working properly
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

// Create logs directory if it doesn't exist
$logs_dir = __DIR__ . '/logs';
if (!is_dir($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}

// Log the execution
$log_message = date('Y-m-d H:i:s') . " - Cron test executed successfully\n";
$log_file = $logs_dir . '/cron_test.log';

file_put_contents($log_file, $log_message, FILE_APPEND);

// Also create a simple output file for easy checking
$output_message = "Cron test executed at: " . date('Y-m-d H:i:s') . "\n";
$output_file = $logs_dir . '/cron_test_output.txt';
file_put_contents($output_file, $output_message, FILE_APPEND);

// Return success (optional)
echo "Cron test completed at: " . date('Y-m-d H:i:s') . "\n";
?> 