<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json');

try {
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get form data
    $username = trim($_POST['username'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate required fields
    $errors = [];
    
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
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    if (empty($confirm_password)) {
        $errors[] = 'Confirm password is required';
    }

    // Validate password requirements
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
    }

    // Check if passwords match
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
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

    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
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

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
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

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (username, first_name, last_name, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
    $stmt->bind_param("ssssss", $username, $first_name, $last_name, $email, $hashed_password, $role);
    
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        
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
        
        $activity_message = "Created new user account";
        $activity_details = "User: $first_name $last_name ($email) with role: $role";
        
        $activity_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, performed_by, message, details) VALUES (?, 'user_created', ?, ?, ?)");
        $activity_stmt->bind_param("isss", $current_user_id, $current_user_name, $activity_message, $activity_details);
        $activity_stmt->execute();
        $activity_stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'User created successfully',
            'user_id' => $user_id
        ]);
    } else {
        throw new Exception('Failed to create user: ' . $stmt->error);
    }
    
    $stmt->close();

} catch (Exception $e) {
    error_log("User creation error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while creating the user. Please try again.'
    ]);
}
?> 