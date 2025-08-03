<?php
/**
 * Test File Structure
 * This script checks the file structure and paths on the server
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

echo "=== File Structure Test ===\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n\n";

// Check current directory
echo "1. Current directory:\n";
echo "   __DIR__: " . __DIR__ . "\n";
echo "   getcwd(): " . getcwd() . "\n\n";

// Check if config directory exists
echo "2. Checking config directory:\n";
$config_dir = __DIR__ . '/../config';
echo "   Config dir path: $config_dir\n";
echo "   Config dir exists: " . (is_dir($config_dir) ? 'YES' : 'NO') . "\n";
if (is_dir($config_dir)) {
    echo "   Config dir contents:\n";
    $files = scandir($config_dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "     - $file\n";
        }
    }
}
echo "\n";

// Check database.php file
echo "3. Checking database.php file:\n";
$db_file = __DIR__ . '/../config/database.php';
echo "   Database file path: $db_file\n";
echo "   Database file exists: " . (file_exists($db_file) ? 'YES' : 'NO') . "\n";
if (file_exists($db_file)) {
    echo "   Database file size: " . filesize($db_file) . " bytes\n";
    echo "   Database file readable: " . (is_readable($db_file) ? 'YES' : 'NO') . "\n";
}
echo "\n";

// Check execute_schedules.php file
echo "4. Checking execute_schedules.php file:\n";
$exec_file = __DIR__ . '/execute_schedules.php';
echo "   Execute file path: $exec_file\n";
echo "   Execute file exists: " . (file_exists($exec_file) ? 'YES' : 'NO') . "\n";
if (file_exists($exec_file)) {
    echo "   Execute file size: " . filesize($exec_file) . " bytes\n";
    echo "   Execute file readable: " . (is_readable($exec_file) ? 'YES' : 'NO') . "\n";
    
    // Check the content around line 23
    echo "   Checking line 23 content:\n";
    $lines = file($exec_file);
    if (isset($lines[22])) { // Line 23 (0-indexed)
        echo "   Line 23: " . trim($lines[22]) . "\n";
    }
    if (isset($lines[21])) { // Line 22
        echo "   Line 22: " . trim($lines[21]) . "\n";
    }
    if (isset($lines[23])) { // Line 24
        echo "   Line 24: " . trim($lines[23]) . "\n";
    }
}
echo "\n";

// Test include paths
echo "5. Testing include paths:\n";
$include_paths = [
    '../config/database.php',
    __DIR__ . '/../config/database.php',
    dirname(__DIR__) . '/config/database.php'
];

foreach ($include_paths as $path) {
    echo "   Testing: $path\n";
    echo "   Exists: " . (file_exists($path) ? 'YES' : 'NO') . "\n";
    if (file_exists($path)) {
        echo "   Readable: " . (is_readable($path) ? 'YES' : 'NO') . "\n";
    }
    echo "\n";
}

echo "=== Test Complete ===\n";
?> 