<?php
header('Content-Type: text/plain');

echo "Testing Database Connection...\n\n";

try {
    // Test database connection
    require_once 'config/database.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();
    echo "✓ Database connection successful\n";

    // Test table existence
    $result = $conn->query("SHOW TABLES LIKE 'water_readings'");
    if ($result->num_rows > 0) {
        echo "✓ Table 'water_readings' exists\n";
    } else {
        echo "✗ Table 'water_readings' does not exist\n";
    }

    // Test data insertion
    $testTurbidity = 50.5;
    $testTDS = 250.75;
    
    $stmt = $conn->prepare("INSERT INTO water_readings (turbidity, tds) VALUES (?, ?)");
    $stmt->bind_param("dd", $testTurbidity, $testTDS);
    
    if ($stmt->execute()) {
        echo "✓ Test data inserted successfully\n";
        
        // Verify the inserted data
        $result = $conn->query("SELECT * FROM water_readings ORDER BY id DESC LIMIT 1");
        if ($row = $result->fetch_assoc()) {
            echo "\nLast inserted record:\n";
            echo "Turbidity: " . $row['turbidity'] . " NTU\n";
            echo "TDS: " . $row['tds'] . " ppm\n";
            echo "Time: " . $row['reading_time'] . "\n";
        }
    } else {
        echo "✗ Failed to insert test data\n";
    }

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?> 