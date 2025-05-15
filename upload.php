<?php
header('Content-Type: application/json');

// Include database configuration
require_once 'config/database.php';

try {
    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Get POST data
    $turbidity = isset($_POST['turbidity']) ? floatval($_POST['turbidity']) : null;
    $tds = isset($_POST['tds']) ? floatval($_POST['tds']) : null;

    // Validate data
    if ($turbidity === null || $tds === null) {
        throw new Exception("Missing required data");
    }

    // Prepare and execute SQL statement
    $stmt = $conn->prepare("INSERT INTO water_readings (turbidity, tds) VALUES (?, ?)");
    $stmt->bind_param("dd", $turbidity, $tds);
    
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Data saved successfully",
            "data" => [
                "turbidity" => $turbidity,
                "tds" => $tds
            ]
        ]);
    } else {
        throw new Exception("Failed to save data");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage()
    ]);
}
?> 