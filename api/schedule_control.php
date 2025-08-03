<?php
// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Set JSON header for API responses
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Create relay_schedules table if it doesn't exist
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS relay_schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        relay_number INT NOT NULL,
        action TINYINT(1) NOT NULL COMMENT '1 for ON, 0 for OFF',
        schedule_date DATE NOT NULL,
        schedule_time TIME NOT NULL,
        frequency ENUM('once', 'daily', 'weekly', 'monthly') DEFAULT 'once',
        is_active TINYINT(1) DEFAULT 1,
        description TEXT,
        last_executed TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_schedule_time (schedule_date, schedule_time),
        INDEX idx_relay (relay_number),
        INDEX idx_active (is_active)
    )";
    
    $conn->query($createTableSQL);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection error']);
    exit;
}

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

// Check for _method parameter (for compatibility with form-based DELETE requests)
if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

switch ($method) {
    case 'GET':
        handleGet($conn);
        break;
    case 'POST':
        handlePost($conn);
        break;
    case 'DELETE':
        handleDelete($conn);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        break;
}

function handleGet($conn) {
    if (isset($_GET['id'])) {
        // Get specific schedule
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT * FROM relay_schedules WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $schedule = $result->fetch_assoc();
            echo json_encode(['success' => true, 'schedule' => $schedule]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Schedule not found']);
        }
        $stmt->close();
    } else {
        // Get all schedules
        $result = $conn->query("SELECT * FROM relay_schedules ORDER BY schedule_date ASC, schedule_time ASC");
        $schedules = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'schedules' => $schedules]);
    }
}

function handlePost($conn) {
    $data = $_POST;
    
    // Debug: Log received data
    error_log("POST data received: " . print_r($data, true));
    
    // Validate required fields
    $required_fields = ['relay_number', 'action', 'schedule_date', 'schedule_time'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            error_log("Missing field: $field");
            echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
            return;
        }
        
        // Special handling for action field (0 is valid)
        if ($field === 'action') {
            if ($data[$field] !== '0' && $data[$field] !== '1' && $data[$field] !== 0 && $data[$field] !== 1) {
                error_log("Invalid action value: " . $data[$field]);
                echo json_encode(['success' => false, 'error' => "Invalid action value. Must be 0 or 1"]);
                return;
            }
        } else {
            // For other fields, check if they're empty
            if (empty($data[$field])) {
                error_log("Empty field: $field");
                echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
                return;
            }
        }
    }
    
    // Validate relay number
    $relay_number = intval($data['relay_number']);
    if ($relay_number < 1 || $relay_number > 4) {
        echo json_encode(['success' => false, 'error' => 'Invalid relay number. Must be 1-4']);
        return;
    }
    
    // Validate action
    $action = intval($data['action']);
    if ($action !== 0 && $action !== 1) {
        echo json_encode(['success' => false, 'error' => 'Invalid action. Must be 0 (OFF) or 1 (ON)']);
        return;
    }
    
    // Validate date and time
    $schedule_date = $data['schedule_date'];
    $schedule_time = $data['schedule_time'];
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $schedule_date)) {
        echo json_encode(['success' => false, 'error' => 'Invalid date format']);
        return;
    }
    
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $schedule_time)) {
        echo json_encode(['success' => false, 'error' => 'Invalid time format']);
        return;
    }
    
    // Validate frequency
    $frequency = $data['frequency'] ?? 'once';
    $valid_frequencies = ['once', 'daily', 'weekly', 'monthly'];
    if (!in_array($frequency, $valid_frequencies)) {
        echo json_encode(['success' => false, 'error' => 'Invalid frequency']);
        return;
    }
    
    // Validate is_active
    $is_active = isset($data['is_active']) ? intval($data['is_active']) : 1;
    if ($is_active !== 0 && $is_active !== 1) {
        echo json_encode(['success' => false, 'error' => 'Invalid active status']);
        return;
    }
    
    // Get description
    $description = $data['description'] ?? '';
    
    if (isset($data['id']) && !empty($data['id'])) {
        // Update existing schedule
        $id = intval($data['id']);
        
        $stmt = $conn->prepare("
            UPDATE relay_schedules 
            SET relay_number = ?, action = ?, schedule_date = ?, schedule_time = ?, 
                frequency = ?, is_active = ?, description = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->bind_param("iisssisi", $relay_number, $action, $schedule_date, $schedule_time, $frequency, $is_active, $description, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update schedule']);
        }
        $stmt->close();
    } else {
        // Create new schedule
        $stmt = $conn->prepare("
            INSERT INTO relay_schedules (relay_number, action, schedule_date, schedule_time, frequency, is_active, description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisssis", $relay_number, $action, $schedule_date, $schedule_time, $frequency, $is_active, $description);
        
        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            echo json_encode(['success' => true, 'message' => 'Schedule created successfully', 'id' => $new_id]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create schedule']);
        }
        $stmt->close();
    }
}

function handleDelete($conn) {
    // Get data from POST (since we're using _method=DELETE)
    $data = $_POST;
    
    // Log for debugging
    error_log("DELETE request data: " . print_r($data, true));
    
    if (isset($data['ids'])) {
        // Bulk delete
        $ids = json_decode($data['ids'], true);
        if (!is_array($ids)) {
            error_log("Invalid IDs format: " . $data['ids']);
            echo json_encode(['success' => false, 'error' => 'Invalid IDs format']);
            return;
        }
        
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $conn->prepare("DELETE FROM relay_schedules WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        
        if ($stmt->execute()) {
            error_log("Bulk delete successful for IDs: " . implode(',', $ids));
            echo json_encode(['success' => true, 'message' => 'Schedules deleted successfully']);
        } else {
            error_log("Bulk delete failed: " . $stmt->error);
            echo json_encode(['success' => false, 'error' => 'Failed to delete schedules']);
        }
        $stmt->close();
    } elseif (isset($data['id'])) {
        // Single delete
        $id = intval($data['id']);
        error_log("Attempting to delete schedule ID: " . $id);
        
        $stmt = $conn->prepare("DELETE FROM relay_schedules WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            error_log("Single delete successful for ID: " . $id);
            echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully']);
        } else {
            error_log("Single delete failed: " . $stmt->error);
            echo json_encode(['success' => false, 'error' => 'Failed to delete schedule']);
        }
        $stmt->close();
    } else {
        error_log("No ID provided for deletion");
        echo json_encode(['success' => false, 'error' => 'No ID provided for deletion']);
    }
}

// Function to execute scheduled tasks (can be called by a cron job)
function executeScheduledTasks($conn) {
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    
    // Get all active schedules that should be executed now
    $stmt = $conn->prepare("
        SELECT * FROM relay_schedules 
        WHERE is_active = 1 
        AND schedule_date <= ? 
        AND (last_executed IS NULL OR last_executed < CONCAT(schedule_date, ' ', schedule_time))
        ORDER BY schedule_date ASC, schedule_time ASC
    ");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $executed = 0;
    while ($schedule = $result->fetch_assoc()) {
        $schedule_datetime = $schedule['schedule_date'] . ' ' . $schedule['schedule_time'];
        
        // Check if it's time to execute this schedule
        if (strtotime($schedule_datetime) <= time()) {
            // Execute the relay control
            $relay_control_result = executeRelayControl($schedule['relay_number'], $schedule['action']);
            
            if ($relay_control_result) {
                // Update last_executed timestamp
                $update_stmt = $conn->prepare("UPDATE relay_schedules SET last_executed = ? WHERE id = ?");
                $update_stmt->bind_param("si", $now, $schedule['id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                $executed++;
                
                // Log the execution
                logScheduleExecution($conn, $schedule, $relay_control_result);
            }
        }
    }
    $stmt->close();
    
    return $executed;
}

function executeRelayControl($relay_number, $action) {
    // This function should call your existing relay_control.php API
    // You can either include the logic here or make an HTTP request to the existing endpoint
    
    $url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/relay_control.php';
    $data = [
        'relay' => $relay_number,
        'state' => $action
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    if ($result !== false) {
        $response = json_decode($result, true);
        return $response && isset($response['success']) && $response['success'];
    }
    
    return false;
}

function logScheduleExecution($conn, $schedule, $result) {
    // Create schedule_logs table if it doesn't exist
    $createLogTableSQL = "
    CREATE TABLE IF NOT EXISTS schedule_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        schedule_id INT NOT NULL,
        relay_number INT NOT NULL,
        action TINYINT(1) NOT NULL,
        execution_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        success TINYINT(1) NOT NULL,
        error_message TEXT,
        FOREIGN KEY (schedule_id) REFERENCES relay_schedules(id) ON DELETE CASCADE
    )";
    
    $conn->query($createLogTableSQL);
    
    // Log the execution
    $stmt = $conn->prepare("
        INSERT INTO schedule_logs (schedule_id, relay_number, action, success)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iiii", $schedule['id'], $schedule['relay_number'], $schedule['action'], $result ? 1 : 0);
    $stmt->execute();
    $stmt->close();
}

// This API file is for CRUD operations only
// Scheduled task execution should be handled by execute_schedules.php
?> 