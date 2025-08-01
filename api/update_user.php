<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get form data
    $user_id = trim($_POST['user_id'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate required fields
    $errors = [];
    
    if (empty($user_id)) {
        $errors[] = 'User ID is required';
    }
    
    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    
    if (empty($first_name)) {
        $errors[] = 'First name is required';
    }
    
    if (empty($last_name)) {
        $errors[] = 'Last name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($role)) {
        $errors[] = 'Role is required';
    } elseif (!in_array($role, ['admin', 'staff'])) {
        $errors[] = 'Invalid role selected';
    }

    // Validate password only if provided
    $updatePassword = false;
    if (!empty($password)) {
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        if (empty($confirm_password)) {
            $errors[] = 'Please confirm your password';
        } elseif ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match';
        } else {
            $updatePassword = true;
        }
    }

    // If there are validation errors, return them
    if (!empty($errors)) {
        echo json_encode([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors
        ]);
        exit;
    }

    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
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
    $stmt->close();

    // Check if username already exists (excluding current user)
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->bind_param("si", $username, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Username already exists'
        ]);
        exit;
    }
    $stmt->close();

    // Check if email already exists (excluding current user)
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Email already exists'
        ]);
        exit;
    }
    $stmt->close();

    // Update user
    if ($updatePassword) {
        // Update with password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET username = ?, first_name = ?, last_name = ?, email = ?, password = ?, role = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $username, $first_name, $last_name, $email, $hashed_password, $role, $user_id);
    } else {
        // Update without password
        $stmt = $conn->prepare("UPDATE users SET username = ?, first_name = ?, last_name = ?, email = ?, role = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $username, $first_name, $last_name, $email, $role, $user_id);
    }
    
    if ($stmt->execute()) {
        $message = $updatePassword ? 'User updated successfully with new password' : 'User updated successfully';
        
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
        $activity_message = "Updated user profile information";
        $password_info = $updatePassword ? " (including password change)" : "";
        $activity_details = "User: $first_name $last_name ($email) with role: $role$password_info";
        
        $activity_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, performed_by, message, details) VALUES (?, 'user_updated', ?, ?, ?)");
        $activity_stmt->bind_param("isss", $current_user_id, $current_user_name, $activity_message, $activity_details);
        $activity_stmt->execute();
        $activity_stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'user_id' => $user_id
        ]);
    } else {
        throw new Exception('Failed to update user: ' . $stmt->error);
    }
    
    $stmt->close();

} catch (Exception $e) {
    error_log("User update error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating the user. Please try again.'
    ]);
}
?> 