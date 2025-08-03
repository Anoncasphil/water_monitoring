<?php
/**
 * Debug Environment Variables
 * This script helps debug environment variable loading
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

echo "=== Environment Debug ===\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n\n";

// Check if .env file exists
$env_file = __DIR__ . '/../.env';
echo "1. Checking .env file:\n";
echo "   File path: $env_file\n";
echo "   File exists: " . (file_exists($env_file) ? 'YES' : 'NO') . "\n";
if (file_exists($env_file)) {
    echo "   File size: " . filesize($env_file) . " bytes\n";
    echo "   File contents:\n";
    $env_contents = file_get_contents($env_file);
    echo "   " . str_replace("\n", "\n   ", $env_contents) . "\n";
}
echo "\n";

// Try to load environment
echo "2. Loading environment variables:\n";
try {
    require_once __DIR__ . '/../config/EnvLoader.php';
    $envLoader = new EnvLoader();
    $envLoader->load(__DIR__ . '/../.env');
    echo "   EnvLoader loaded successfully\n";
} catch (Exception $e) {
    echo "   Error loading EnvLoader: " . $e->getMessage() . "\n";
}
echo "\n";

// Check CRON_SECRET_KEY
echo "3. Checking CRON_SECRET_KEY:\n";
$cron_key = getenv('CRON_SECRET_KEY');
echo "   CRON_SECRET_KEY value: " . ($cron_key ? $cron_key : 'NOT SET') . "\n";
echo "   CRON_SECRET_KEY length: " . strlen($cron_key) . "\n";
echo "\n";

// Check all environment variables
echo "4. All environment variables:\n";
$env_vars = getenv();
foreach ($env_vars as $key => $value) {
    if (strpos($key, 'CRON_') === 0 || strpos($key, 'DB_') === 0) {
        echo "   $key = $value\n";
    }
}
echo "\n";

// Test the secret key check
echo "5. Testing secret key validation:\n";
$test_key = 'secret';
$provided_key = 'a4f2c1b3e9174f0cb334fd442f7a2a8c';
echo "   Expected key: $test_key\n";
echo "   Provided key: $provided_key\n";
echo "   Keys match: " . ($test_key === $provided_key ? 'YES' : 'NO') . "\n";
echo "   CRON_SECRET_KEY matches expected: " . ($cron_key === $test_key ? 'YES' : 'NO') . "\n";
echo "   CRON_SECRET_KEY matches provided: " . ($cron_key === $provided_key ? 'YES' : 'NO') . "\n";

echo "\n=== Debug Complete ===\n";
?> 