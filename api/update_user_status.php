<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $user_id = trim($_POST['user_id'] ?? '');
    $action = trim($_POST['action'] ?? '');

    // Validate required fields
    if (empty($user_id)) {
        throw new Exception('User ID is required');
    }

    if (empty($action) || !in_array($action, ['archive', 'activate'])) {
        throw new Exception('Invalid action');
    }

    // Determine new status
    $new_status = ($action === 'archive') ? 'inactive' : 'active';

    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Check if user exists
    $stmt = $conn->prepare("SELECT id, username, first_name, last_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();

    // Update user status
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $user_id);
    
    if ($stmt->execute()) {
        $action_text = ($action === 'archive') ? 'deactivated' : 'activated';
        $message = "User {$user['first_name']} {$user['last_name']} has been {$action_text} successfully.";
        
        // Log the activity
        $current_user_id = $_SESSION['user_id'] ?? null;
        $current_user_name = 'System';
        
        // Get current user's full name
        if ($current_user_id) {
            $name_stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?");
            $name_stmt->bind_param("i", $current_user_id);
            $name_stmt->execute();
            $name_result = $name_stmt->get_result();
            if ($name_row = $name_result->fetch_assoc()) {
                $current_user_name = $name_row['full_name'];
            }
            $name_stmt->close();
        }
        
        // Determine activity log details
        $activity_action_type = ($action === 'archive') ? 'user_archived' : 'user_activated';
        $activity_message = ($action === 'archive') ? 'Archived user account' : 'Activated user account';
        $activity_details = "User: {$user['first_name']} {$user['last_name']} ({$user['username']}) - Account {$action_text}";
        
        $activity_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, performed_by, message, details) VALUES (?, ?, ?, ?, ?)");
        $activity_stmt->bind_param("issss", $current_user_id, $activity_action_type, $current_user_name, $activity_message, $activity_details);
        $activity_stmt->execute();
        $activity_stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'user_id' => $user_id,
            'new_status' => $new_status
        ]);
    } else {
        throw new Exception('Failed to update user status: ' . $stmt->error);
    }
    
    $stmt->close();

} catch (Exception $e) {
    error_log("User status update error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating user status. Please try again.'
    ]);
}
?> 