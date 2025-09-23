<?php
// Simple test script for relay API without database
header('Content-Type: application/json; charset=utf-8');

// Mock relay states for testing
$mockStates = [
    ["relay_number" => 1, "state" => 0, "manual_override" => 0],
    ["relay_number" => 2, "state" => 0, "manual_override" => 0], 
    ["relay_number" => 3, "state" => 0, "manual_override" => 0],
    ["relay_number" => 4, "state" => 0, "manual_override" => 0]
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        "success" => true,
        "states" => $mockStates,
        "message" => "Mock relay states - all OFF"
    ]);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $relay = isset($_POST['relay']) ? (int)$_POST['relay'] : null;
    $state = isset($_POST['state']) ? (int)$_POST['state'] : null;
    
    if ($relay >= 1 && $relay <= 4 && ($state === 0 || $state === 1)) {
        // Update mock state
        $mockStates[$relay - 1]['state'] = $state;
        $mockStates[$relay - 1]['manual_override'] = $state;
        
        echo json_encode([
            "success" => true,
            "message" => "Mock relay {$relay} set to " . ($state ? "ON" : "OFF"),
            "states" => $mockStates
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            "error" => "Invalid relay or state value"
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        "error" => "Method not allowed"
    ]);
}
?>
