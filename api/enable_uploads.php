<?php
// Simple script to enable uploads for Arduino
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Enable uploads by setting the system setting
    $stmt = $conn->prepare("INSERT INTO system_settings (name, value, description, created_at, updated_at) VALUES ('uploads_disabled', '0', 'Enable/disable data uploads from Arduino', NOW(), NOW()) ON DUPLICATE KEY UPDATE value = '0', updated_at = NOW()");
    
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
