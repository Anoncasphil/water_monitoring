<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $email = trim($_POST['email'] ?? '');
    $user_id = trim($_POST['user_id'] ?? ''); // For editing mode

    if (empty($email)) {
        echo json_encode([
            'available' => false,
            'message' => 'Email is required'
        ]);
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'available' => false,
            'message' => 'Invalid email format'
        ]);
        exit;
    }

    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Check if email exists
    if (!empty($user_id)) {
        // For editing: exclude current user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
    } else {
        // For new user: check all users
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode([
            'available' => false,
            'message' => 'Email is already taken'
        ]);
    } else {
        echo json_encode([
            'available' => true,
            'message' => 'Email is available'
        ]);
    }
    
    $stmt->close();

} catch (Exception $e) {
    error_log("Email check error: " . $e->getMessage());
    echo json_encode([
        'available' => false,
        'message' => 'Error checking email availability'
    ]);
}
?> 