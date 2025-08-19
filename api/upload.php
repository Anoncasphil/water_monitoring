<?php
header('Content-Type: application/json');

// Include database configuration
require_once '../config/database.php';

try {
    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Check if uploads are disabled via system settings
    $uploadsDisabled = false;
    try {
        $result = $conn->query("SELECT `value` AS v FROM `system_settings` WHERE `name` = 'uploads_disabled' LIMIT 1");
        if ($result && $row = $result->fetch_assoc()) {
            $uploadsDisabled = ($row['v'] === '1');
        }
    } catch (Exception $e) {
        // If the settings table does not exist or query fails, default to uploads enabled
    }

    if ($uploadsDisabled) {
        http_response_code(403);
        echo json_encode([
            "error" => "Uploads are currently disabled"
        ]);
        exit;
    }

    // Get POST data
    $turbidity = isset($_POST['turbidity']) ? floatval($_POST['turbidity']) : null;
    $tds = isset($_POST['tds']) ? floatval($_POST['tds']) : null;
    $ph = isset($_POST['ph']) ? floatval($_POST['ph']) : null;
    $temperature = isset($_POST['temperature']) ? floatval($_POST['temperature']) : null;
    $in = isset($_POST['in']) ? floatval($_POST['in']) : 0; // Default to 0 if not provided

    // Validate data
    if ($turbidity === null || $tds === null || $ph === null || $temperature === null) {
        throw new Exception("Missing required data");
    }

    // Prepare and execute SQL statement
    $stmt = $conn->prepare("INSERT INTO water_readings (turbidity, tds, ph, temperature, `in`) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ddddd", $turbidity, $tds, $ph, $temperature, $in);
    
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Data saved successfully",
            "data" => [
                "turbidity" => $turbidity,
                "tds" => $tds,
                "ph" => $ph,
                "temperature" => $temperature,
                "in" => $in
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