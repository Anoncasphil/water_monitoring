<?php
/**
 * Check Files on Server
 * This script lists all files in the api directory
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

echo "=== Server Files Check ===\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n\n";

// Check current directory
echo "1. Current directory:\n";
echo "   __DIR__: " . __DIR__ . "\n";
echo "   getcwd(): " . getcwd() . "\n\n";

// List all files in the api directory
echo "2. Files in api directory:\n";
$files = scandir(__DIR__);
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        $file_path = __DIR__ . '/' . $file;
        $is_file = is_file($file_path);
        $is_readable = is_readable($file_path);
        $size = $is_file ? filesize($file_path) : 0;
        echo "   - $file (file: " . ($is_file ? 'YES' : 'NO') . ", readable: " . ($is_readable ? 'YES' : 'NO') . ", size: $size bytes)\n";
    }
}
echo "\n";

// Check specific files
echo "3. Checking specific files:\n";
$specific_files = [
    'execute_schedules.php',
    'execute_schedules_fixed.php',
    'test_file_structure.php',
    'relay_control.php',
    'schedule_control.php'
];

foreach ($specific_files as $file) {
    $file_path = __DIR__ . '/' . $file;
    echo "   $file: " . (file_exists($file_path) ? 'EXISTS' : 'MISSING') . "\n";
}
echo "\n";

// Check if we can execute PHP
echo "4. PHP execution test:\n";
echo "   PHP version: " . phpversion() . "\n";
echo "   Current user: " . get_current_user() . "\n";
echo "   Script owner: " . fileowner(__FILE__) . "\n";
echo "   Script permissions: " . substr(sprintf('%o', fileperms(__FILE__)), -4) . "\n";

echo "\n=== Check Complete ===\n";
?> 