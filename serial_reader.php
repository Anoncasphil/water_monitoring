<?php
require_once 'config/database.php';

// Function to read serial data
function readSerialData() {
    // Open serial port (COM3 for Windows)
    $serial = fopen("COM3", "r+b");
    if (!$serial) {
        die("Failed to open serial port");
    }

    echo "Reading serial data...\n";

    while (true) {
        // Read data from serial port
        $data = fgets($serial);
        
        // Check if data starts with "DATA:"
        if (strpos($data, "DATA:") === 0) {
            // Extract values
            $values = explode(",", substr($data, 5));
            if (count($values) === 2) {
                $turbidity = floatval(trim($values[0]));
                $tds = floatval(trim($values[1]));
                
                try {
                    // Save to database
                    $db = Database::getInstance();
                    $conn = $db->getConnection();
                    
                    $stmt = $conn->prepare("INSERT INTO water_readings (turbidity, tds) VALUES (?, ?)");
                    $stmt->bind_param("dd", $turbidity, $tds);
                    
                    if ($stmt->execute()) {
                        echo "Data saved: Turbidity=$turbidity NTU, TDS=$tds ppm\n";
                    } else {
                        echo "Failed to save data\n";
                    }
                } catch (Exception $e) {
                    echo "Database error: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    fclose($serial);
}

// Start reading
readSerialData();
?> 