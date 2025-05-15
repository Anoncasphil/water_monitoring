<?php
require_once 'config/Database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Create water_quality table
    $sql = "CREATE TABLE IF NOT EXISTS water_quality (
        id INT AUTO_INCREMENT PRIMARY KEY,
        turbidity FLOAT NOT NULL,
        tds FLOAT NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    if ($conn->query($sql) === TRUE) {
        echo "Table water_quality created successfully\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 