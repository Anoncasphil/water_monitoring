<?php
header('Content-Type: application/json');
require_once '../config/database.php';

function map_reading($row) {
    return [
        'turbidity_ntu' => $row['turbidity'],
        'tds_ppm' => $row['tds'],
        'ph' => $row['ph'],
        'temperature' => $row['temperature'],
        'reading_time' => $row['reading_time']
    ];
}

try {
    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Get second-to-last reading (skip the latest one)
    $latestQuery = "SELECT * FROM water_readings ORDER BY reading_time DESC LIMIT 1 OFFSET 1";
    $latestResult = $conn->query($latestQuery);
    $latest = $latestResult->fetch_assoc();
    $latest = $latest ? map_reading($latest) : null;

    // Get recent readings (last 10, excluding the latest one)
    $recentQuery = "SELECT * FROM water_readings ORDER BY reading_time DESC LIMIT 10 OFFSET 1";
    $recentResult = $conn->query($recentQuery);
    $recent = [];
    while ($row = $recentResult->fetch_assoc()) {
        $recent[] = map_reading($row);
    }

    // Get historical data (last 24 readings, excluding the latest one)
    $historicalQuery = "SELECT * FROM water_readings ORDER BY reading_time DESC LIMIT 24 OFFSET 1";
    $historicalResult = $conn->query($historicalQuery);
    $historical = [];
    while ($row = $historicalResult->fetch_assoc()) {
        $historical[] = map_reading($row);
    }

    // Prepare response
    $response = [
        'latest' => $latest,
        'recent' => $recent,
        'historical' => array_reverse($historical) // Reverse to show oldest to newest
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
} 