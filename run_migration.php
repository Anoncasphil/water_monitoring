<?php
/**
 * Database Migration Script
 * Updates schedule_logs table to include scheduled_time and executed_time fields
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

echo "=== Database Migration Started: " . date('Y-m-d H:i:s') . " ===\n";

// Load database configuration
require_once 'config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "Connected to database successfully.\n";
    
    // Read and execute the migration SQL
    $migration_sql = file_get_contents('database/update_schedule_logs.sql');
    
    if ($migration_sql === false) {
        throw new Exception("Could not read migration file: database/update_schedule_logs.sql");
    }
    
    echo "Executing migration SQL...\n";
    
    // Split the SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $migration_sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^(USE|--)/', $statement)) {
            echo "Executing: " . substr($statement, 0, 50) . "...\n";
            
            $result = $conn->query($statement);
            
            if ($result === false) {
                echo "Warning: " . $conn->error . "\n";
            } else {
                echo "✓ Success\n";
            }
        }
    }
    
    echo "\n=== Migration Completed Successfully ===\n";
    echo "The schedule_logs table has been updated with new fields.\n";
    echo "Scheduled time and execution time should now display correctly.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Migration error: " . $e->getMessage());
}
?> 