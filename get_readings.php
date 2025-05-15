<?php
header('Content-Type: application/json');

$db_config = [
    'host' => 'localhost',
    'user' => 'root',
    'password' => '',
    'database' => 'water_quality_db'
];

try {
    $conn = new mysqli($db_config['host'], $db_config['user'], $db_config['password'], $db_config['database']);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get latest reading
    $latest = $conn->query("
        SELECT 
            turbidity_ntu,
            tds_ppm,
            DATE_FORMAT(reading_time, '%Y-%m-%d %H:%i:%s') as reading_time
        FROM water_quality_readings 
        ORDER BY reading_time DESC 
        LIMIT 1
    ")->fetch_assoc();
    
    // Get recent readings (last 10)
    $recent = $conn->query("
        SELECT 
            turbidity_ntu,
            tds_ppm,
            DATE_FORMAT(reading_time, '%Y-%m-%d %H:%i:%s') as reading_time
        FROM water_quality_readings 
        ORDER BY reading_time DESC 
        LIMIT 10
    ")->fetch_all(MYSQLI_ASSOC);
    
    // Get historical data (last 24 hours)
    $historical = $conn->query("
        SELECT 
            turbidity_ntu,
            tds_ppm,
            DATE_FORMAT(reading_time, '%Y-%m-%d %H:%i:%s') as reading_time
        FROM water_quality_readings 
        WHERE reading_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY reading_time ASC
    ")->fetch_all(MYSQLI_ASSOC);

    // Debug information
    error_log("Latest reading: " . json_encode($latest));
    error_log("Recent readings count: " . count($recent));
    error_log("Historical readings count: " . count($historical));

    if (!$latest) {
        throw new Exception("No readings found in the database");
    }

    echo json_encode([
        'latest' => $latest,
        'recent' => $recent,
        'historical' => $historical
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
} 