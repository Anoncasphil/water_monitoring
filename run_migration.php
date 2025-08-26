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
    
    // Read and execute the migration SQL files
    $migrationFiles = [
        'database/update_schedule_logs.sql',
        'database/11_retention_archival.sql'
    ];
    
    foreach ($migrationFiles as $file) {
        echo "\n--- Executing migration file: {$file} ---\n";
        $migration_sql = file_get_contents($file);
        if ($migration_sql === false) {
            echo "⚠️  Skipping: Could not read migration file: {$file}\n";
            continue;
        }

        // Split the SQL into individual statements
        $statements = array_filter(array_map('trim', explode(';', $migration_sql)));

        foreach ($statements as $index => $statement) {
            if (!empty($statement) && !preg_match('/^(USE|--)/', $statement)) {
                echo "Executing statement " . ($index + 1) . ": " . substr($statement, 0, 60) . "...\n";

                $result = $conn->query($statement);

                if ($result === false) {
                    echo "❌ Error: " . $conn->error . "\n";
                    echo "Statement: " . $statement . "\n";
                    // Continue with other statements even if one fails
                    continue;
                } else {
                    echo "✓ Success\n";
                }
            }
        }
    }
    
    echo "\n=== Migration Completed ===\n";
    echo "The schedule_logs table has been updated with new fields.\n";
    echo "Scheduled time and execution time should now display correctly.\n";
    
    // Show final table structure
    echo "\n=== Final Table Structure ===\n";
    $result = $conn->query("DESCRIBE schedule_logs");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . " | " . $row['Key'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Migration error: " . $e->getMessage());
}
?> 