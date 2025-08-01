<?php
require_once '../config/database.php';

try {
    $conn = new mysqli($db_config['host'], $db_config['user'], $db_config['password'], $db_config['database']);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Check if table exists
    $result = $conn->query("SHOW TABLES LIKE 'water_quality_readings'");
    if ($result->num_rows == 0) {
        echo "Table 'water_quality_readings' does not exist!<br>";
        // Create the table
        $sql = file_get_contents('create_table.sql');
        if ($conn->query($sql)) {
            echo "Table created successfully!<br>";
        } else {
            echo "Error creating table: " . $conn->error . "<br>";
        }
    } else {
        echo "Table exists.<br>";
    }

    // Count total records
    $result = $conn->query("SELECT COUNT(*) as count FROM water_quality_readings");
    $count = $result->fetch_assoc()['count'];
    echo "Total records: " . $count . "<br>";

    // Show latest 5 records
    echo "<h3>Latest 5 records:</h3>";
    $result = $conn->query("SELECT * FROM water_quality_readings ORDER BY reading_time DESC LIMIT 5");
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Turbidity (NTU)</th><th>TDS (ppm)</th><th>Time</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['turbidity_ntu'] . "</td>";
        echo "<td>" . $row['tds_ppm'] . "</td>";
        echo "<td>" . $row['reading_time'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} finally {
    if (isset($conn)) {
        $conn->close();
    }
} 