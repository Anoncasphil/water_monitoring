<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo json_encode([
        "success" => true,
        "message" => "Database connection successful",
        "server_time" => date('Y-m-d H:i:s'),
        "php_version" => PHP_VERSION
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?> 