<?php
// Simple script to enable uploads for Arduino
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Enable uploads by setting the system setting (simplified schema)
    $stmt = $conn->prepare("INSERT INTO system_settings (name, value) VALUES ('uploads_disabled', '0') ON DUPLICATE KEY UPDATE value = '0'");
    
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Uploads enabled successfully",
            "timestamp" => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "error" => "Failed to enable uploads"
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
