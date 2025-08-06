<?php
// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login/index.php');
    exit;
}

require_once '../../config/database.php';

// Get existing schedules
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
    
    $result = $conn->query("SELECT * FROM relay_schedules ORDER BY schedule_date ASC, schedule_time ASC");
    $schedules = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
} catch (Exception $e) {
    error_log("Schedule page error: " . $e->getMessage());
    $schedules = [];
}

// Get schedule logs with pagination
$logs_per_page = isset($_GET['logs_per_page']) ? max(10, min(100, intval($_GET['logs_per_page']))) : 20;
$current_page = isset($_GET['logs_page']) ? max(1, intval($_GET['logs_page'])) : 1;
$offset = ($current_page - 1) * $logs_per_page;

try {
    // Get total count for pagination
    $count_result = $conn->query("SELECT COUNT(*) as total FROM schedule_logs");
    $total_logs = $count_result ? $count_result->fetch_assoc()['total'] : 0;
    $total_pages = ceil($total_logs / $logs_per_page);
    
    // Get logs with pagination
    $logs_sql = "
        SELECT sl.* 
        FROM schedule_logs sl 
        ORDER BY sl.executed_time DESC 
        LIMIT ? OFFSET ?
    ";
    $logs_stmt = $conn->prepare($logs_sql);
    $logs_stmt->bind_param("ii", $logs_per_page, $offset);
    $logs_stmt->execute();
    $logs_result = $logs_stmt->get_result();
    $schedule_logs = $logs_result ? $logs_result->fetch_all(MYSQLI_ASSOC) : [];
    $logs_stmt->close();
} catch (Exception $e) {
    error_log("Schedule logs error: " . $e->getMessage());
    $schedule_logs = [];
    $total_logs = 0;
    $total_pages = 0;
}

// Get relay names for display
$relayNames = [
    1 => 'Filter',
    2 => 'Dispense Water'
];
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - Water Quality System</title>
    <link rel="icon" type="image/png" href="../../assets/images/icons/icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
        // Theme initialization - must run before page renders to prevent flash
        (function() {
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            bg: '#0F172A',
                            card: '#1E293B',
                            text: '#F8FAFC',
                            muted: '#94A3B8'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .schedule-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .schedule-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #3B82F6, #10B981, #8B5CF6, #EF4444);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        .schedule-card:hover::before {
            transform: scaleX(1);
        }
        .schedule-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .dark .schedule-card:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #F8FAFC 0%, #E2E8F0 100%);
        }
        .dark .gradient-bg {
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .status-active {
            background-color: #DEF7EC;
            color: #03543F;
        }
        .dark .status-active {
            background-color: #065F46;
            color: #D1FAE5;
        }
        .status-pending {
            background-color: #FEF3C7;
            color: #92400E;
        }
        .dark .status-pending {
            background-color: #92400E;
            color: #FEF3C7;
        }
        .status-completed {
            background-color: #E0E7FF;
            color: #3730A3;
        }
        .dark .status-completed {
            background-color: #3730A3;
            color: #E0E7FF;
        }
        .status-overdue {
            background-color: #FEE2E2;
            color: #DC2626;
        }
        .dark .status-overdue {
            background-color: #DC2626;
            color: #FEE2E2;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 1rem;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: modalSlideIn 0.3s ease-out;
        }
        .dark .modal-content {
            background-color: #1E293B;
        }
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .close:hover,
        .close:focus {
            color: #000;
        }
        .dark .close:hover,
        .dark .close:focus {
            color: #fff;
        }
    </style>
</head>
<body class="gradient-bg min-h-screen transition-colors duration-300">
    <!-- Include Sidebar -->
    <?php include '../sidebar/sidebar.php'; ?>
    
    <!-- Schedules data for JavaScript -->
    <script>
        window.schedulesData = <?php 
            try {
                echo json_encode($schedules ?? [], JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES);
            } catch (Exception $e) {
                echo '[]';
            }
        ?>;
    </script>
    
    <!-- Main Content -->
    <div class="lg:ml-64">
        <div class="container mx-auto px-6 py-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-10">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                        <i class="fas fa-calendar-alt text-blue-500 mr-3"></i>
                        Schedule Management
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 text-lg">Schedule automated relay operations for optimal water quality control</p>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- Real-time Clock -->
                    <div class="text-center bg-white dark:bg-gray-800 rounded-xl shadow-lg px-4 py-2">
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Current Time</div>
                        <div class="text-lg font-semibold text-gray-900 dark:text-white" id="currentTime">--:--:--</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400" id="currentDate">--</div>
                    </div>
                    
                    <button id="themeToggle" class="p-3 rounded-xl bg-white dark:bg-gray-800 shadow-lg hover:shadow-xl transition-all duration-200">
                        <i class="fas fa-sun text-yellow-500 dark:hidden text-lg"></i>
                        <i class="fas fa-moon text-blue-300 hidden dark:block text-lg"></i>
                    </button>
                    <button id="addScheduleBtn" class="px-6 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-semibold transition-colors duration-200 flex items-center space-x-2">
                        <i class="fas fa-plus"></i>
                        <span>Add Schedule</span>
                    </button>
                </div>
            </div>

            <!-- Schedule Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="schedule-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Total Schedules</h3>
                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400" id="totalSchedules"><?php echo count($schedules); ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                            <i class="fas fa-calendar text-blue-500 dark:text-blue-400 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="schedule-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Active Schedules</h3>
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400" id="activeSchedules">0</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                            <i class="fas fa-play text-green-500 dark:text-green-400 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="schedule-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Today's Tasks</h3>
                            <p class="text-2xl font-bold text-purple-600 dark:text-purple-400" id="todayTasks">0</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                            <i class="fas fa-tasks text-purple-500 dark:text-purple-400 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="schedule-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Next Execution</h3>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400" id="nextExecution">No pending tasks</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                            <i class="fas fa-clock text-orange-500 dark:text-orange-400 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedule List -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                            <i class="fas fa-list text-blue-500 mr-3"></i>
                            Scheduled Operations
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400">Manage automated relay control schedules</p>
                    </div>
                    <div class="flex space-x-3">
                        <button id="refreshSchedules" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors">
                            <i class="fas fa-sync-alt mr-2"></i>Refresh
                        </button>
                        <button id="bulkDelete" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors">
                            <i class="fas fa-trash mr-2"></i>Delete Selected
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">
                                    <input type="checkbox" id="selectAll" class="rounded border-gray-300">
                                </th>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Relay</th>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Action</th>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Schedule Time</th>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Frequency</th>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Status</th>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Last Run</th>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="scheduleTableBody">
                            <?php if (empty($schedules)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center space-y-4">
                                        <i class="fas fa-calendar-times text-4xl"></i>
                                        <div>
                                            <p class="text-lg font-medium">No schedules found</p>
                                            <p class="text-sm">Create your first schedule to automate relay operations</p>
                                        </div>
                                        <button id="createFirstSchedule" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors">
                                            <i class="fas fa-plus mr-2"></i>Create Schedule
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($schedules as $schedule): ?>
                                <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" class="schedule-checkbox rounded border-gray-300" value="<?php echo $schedule['id']; ?>">
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                                <i class="fas fa-plug text-blue-500 dark:text-blue-400 text-sm"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-900 dark:text-white"><?php echo $relayNames[$schedule['relay_number']] ?? 'Unknown'; ?></div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">Relay <?php echo $schedule['relay_number']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $schedule['action'] == 1 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'; ?>">
                                            <?php echo $schedule['action'] == 1 ? 'Turn ON' : 'Turn OFF'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            <?php echo date('M j, Y', strtotime($schedule['schedule_date'])); ?>
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo date('g:i A', strtotime($schedule['schedule_time'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-sm text-gray-900 dark:text-white">
                                            <?php 
                                            switch($schedule['frequency']) {
                                                case 'once': echo 'Once'; break;
                                                case 'daily': echo 'Daily'; break;
                                                case 'weekly': echo 'Weekly'; break;
                                                case 'monthly': echo 'Monthly'; break;
                                                default: echo 'Custom'; break;
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php 
                                        $status = 'pending';
                                        $statusClass = 'status-pending';
                                        if ($schedule['is_active'] == 0) {
                                            $status = 'inactive';
                                            $statusClass = 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                                        } elseif ($schedule['last_executed'] !== null) {
                                            // Schedule has been executed
                                            $status = 'completed';
                                            $statusClass = 'status-completed';
                                        } elseif (strtotime($schedule['schedule_date'] . ' ' . $schedule['schedule_time']) < time()) {
                                            // Schedule time has passed but not executed yet
                                            $status = 'overdue';
                                            $statusClass = 'status-overdue';
                                        } else {
                                            $status = 'active';
                                            $statusClass = 'status-active';
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            <?php echo $schedule['last_executed'] ? date('M j, g:i A', strtotime($schedule['last_executed'])) : 'Never'; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-2">
                                            <button onclick="editSchedule(<?php echo $schedule['id']; ?>)" class="p-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteSchedule(<?php echo $schedule['id']; ?>, '<?php echo addslashes($relayNames[$schedule['relay_number']] ?? 'Unknown'); ?> - <?php echo $schedule['action'] == 1 ? 'ON' : 'OFF'; ?>')" class="p-2 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Schedule Logs Section -->
        <div class="container mx-auto px-6 py-8">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                        <i class="fas fa-history text-blue-500 mr-3"></i>
                        Execution Logs
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400">Track schedule execution history and performance</p>
                </div>
                <div class="flex space-x-3">
                    <button id="refreshLogs" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors">
                        <i class="fas fa-sync-alt mr-2"></i>Refresh Logs
                    </button>
                    <button id="clearLogs" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors">
                        <i class="fas fa-trash mr-2"></i>Clear All Logs
                    </button>
                </div>
            </div>



            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-800">
                        <tr>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-600">Schedule ID</th>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-600">Relay</th>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-600">Action</th>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-600">Scheduled Time</th>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-600">Execution Time</th>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-600">Status</th>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-600">Details</th>
                        </tr>
                    </thead>
                    <tbody id="logsTableBody">
                        <?php if (empty($schedule_logs)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                                <div class="flex flex-col items-center space-y-4">
                                    <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                        <i class="fas fa-history text-2xl text-gray-400 dark:text-gray-500"></i>
                                    </div>
                                    <div>
                                        <p class="text-lg font-medium">No execution logs found</p>
                                        <p class="text-sm">Logs will appear here when schedules are executed</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($schedule_logs as $log): ?>
                            <tr class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-all duration-200 group">
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                            <span class="text-sm font-semibold text-blue-600 dark:text-blue-400">#<?php echo $log['schedule_id']; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-100 to-blue-200 dark:from-blue-900/40 dark:to-blue-800/40 flex items-center justify-center shadow-sm">
                                            <i class="fas fa-plug text-blue-600 dark:text-blue-400"></i>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900 dark:text-white"><?php echo $relayNames[$log['relay_number']] ?? 'Unknown'; ?></div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Relay <?php echo $log['relay_number']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold <?php echo $log['action'] == 1 ? 'bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 dark:from-green-900/30 dark:to-emerald-900/30 dark:text-green-300' : 'bg-gradient-to-r from-red-100 to-pink-100 text-red-800 dark:from-red-900/30 dark:to-pink-900/30 dark:text-red-300'; ?>">
                                        <i class="fas <?php echo $log['action'] == 1 ? 'fa-power-off' : 'fa-stop'; ?> mr-1.5"></i>
                                        <?php echo $log['action'] == 1 ? 'Turn ON' : 'Turn OFF'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                        <?php 
                                        // Check if scheduled_time field exists in the table
                                        $check_scheduled_time = $conn->query("SHOW COLUMNS FROM schedule_logs LIKE 'scheduled_time'");
                                        
                                        if ($check_scheduled_time && $check_scheduled_time->num_rows > 0 && !empty($log['scheduled_time'])) {
                                            // New table structure with scheduled_time field
                                            echo date('M j, Y', strtotime($log['scheduled_time']));
                                        } else {
                                            // Fallback to getting scheduled time from relay_schedules table
                                            $schedule_query = "SELECT schedule_date, schedule_time FROM relay_schedules WHERE id = ?";
                                            $schedule_stmt = $conn->prepare($schedule_query);
                                            $schedule_stmt->bind_param("i", $log['schedule_id']);
                                            $schedule_stmt->execute();
                                            $schedule_result = $schedule_stmt->get_result();
                                            $schedule_data = $schedule_result->fetch_assoc();
                                            $schedule_stmt->close();
                                            
                                            if ($schedule_data) {
                                                $scheduled_datetime = $schedule_data['schedule_date'] . ' ' . $schedule_data['schedule_time'];
                                                echo date('M j, Y', strtotime($scheduled_datetime));
                                            } else {
                                                echo 'N/A';
                                            }
                                        }
                                        ?>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-clock mr-1"></i>
                                        <?php 
                                        if ($check_scheduled_time && $check_scheduled_time->num_rows > 0 && !empty($log['scheduled_time'])) {
                                            // New table structure with scheduled_time field
                                            echo date('g:i A', strtotime($log['scheduled_time']));
                                        } else {
                                            // Fallback to getting scheduled time from relay_schedules table
                                            if (isset($schedule_data) && $schedule_data) {
                                                echo date('g:i A', strtotime($scheduled_datetime));
                                            } else {
                                                echo 'N/A';
                                            }
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                        <?php 
                                        // Check if executed_time field exists in the table
                                        $check_executed_time = $conn->query("SHOW COLUMNS FROM schedule_logs LIKE 'executed_time'");
                                        
                                        if ($check_executed_time && $check_executed_time->num_rows > 0 && !empty($log['executed_time'])) {
                                            // New table structure with executed_time field
                                            echo date('M j, Y', strtotime($log['executed_time']));
                                        } else {
                                            // Fallback to execution_time field
                                            echo date('M j, Y', strtotime($log['execution_time']));
                                        }
                                        ?>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-play mr-1"></i>
                                        <?php 
                                        if ($check_executed_time && $check_executed_time->num_rows > 0 && !empty($log['executed_time'])) {
                                            // New table structure with executed_time field
                                            echo date('g:i A', strtotime($log['executed_time']));
                                        } else {
                                            // Fallback to execution_time field
                                            echo date('g:i A', strtotime($log['execution_time']));
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($log['success'] == 1): ?>
                                    <div class="flex items-center space-x-2">
                                        <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                                        <span class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold bg-gradient-to-r from-green-50 to-emerald-50 text-green-700 dark:from-green-900/20 dark:to-emerald-900/20 dark:text-green-300 border border-green-200 dark:border-green-800 shadow-sm">
                                            <i class="fas fa-check-circle mr-2 text-green-600 dark:text-green-400"></i>
                                            Successfully Executed
                                        </span>
                                    </div>
                                    <?php else: ?>
                                    <div class="flex items-center space-x-2">
                                        <div class="w-3 h-3 bg-red-500 rounded-full animate-pulse"></div>
                                        <span class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold bg-gradient-to-r from-red-50 to-pink-50 text-red-700 dark:from-red-900/20 dark:to-pink-900/20 dark:text-red-300 border border-red-200 dark:border-red-800 shadow-sm">
                                            <i class="fas fa-times-circle mr-2 text-red-600 dark:text-red-400"></i>
                                            Failed to Execute
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-white max-w-xs">
                                        <?php 
                                        // Use error_message field (the actual field name in the table)
                                        $error_details = !empty($log['error_message']) ? $log['error_message'] : '';
                                        
                                        if ($error_details): ?>
                                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-2">
                                            <div class="flex items-start space-x-2">
                                                <i class="fas fa-exclamation-triangle text-red-500 dark:text-red-400 mt-0.5 text-xs"></i>
                                                <span class="text-xs text-red-700 dark:text-red-300"><?php echo htmlspecialchars($error_details); ?></span>
                                            </div>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-gray-400 dark:text-gray-500 text-xs">No errors</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Enhanced Pagination -->
            <div class="mt-8">
                <!-- Pagination Info -->
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-4">
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                            <span class="font-semibold"><?php echo $total_logs; ?></span> total logs
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Showing <span class="font-semibold"><?php echo ($offset + 1); ?></span> to <span class="font-semibold"><?php echo min($offset + $logs_per_page, $total_logs); ?></span>
                        </div>
                    </div>
                    
                    <!-- Page Size Selector -->
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Show:</span>
                        <select id="pageSize" class="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                            <option value="10" <?php echo $logs_per_page == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="20" <?php echo $logs_per_page == 20 ? 'selected' : ''; ?>>20</option>
                            <option value="50" <?php echo $logs_per_page == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $logs_per_page == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                        <span class="text-sm text-gray-600 dark:text-gray-400">per page</span>
                    </div>
                </div>

                <!-- Pagination Controls -->
                <?php if ($total_pages > 1): ?>
                <div class="flex items-center justify-center space-x-2">
                    <!-- First Page -->
                    <?php if ($current_page > 1): ?>
                    <a href="?logs_page=1&logs_per_page=<?php echo $logs_per_page; ?>" class="px-3 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg transition-colors shadow-sm">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Previous Page -->
                    <?php if ($current_page > 1): ?>
                    <a href="?logs_page=<?php echo $current_page - 1; ?>&logs_per_page=<?php echo $logs_per_page; ?>" class="px-3 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg transition-colors shadow-sm">
                        <i class="fas fa-angle-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <div class="flex space-x-1">
                        <?php 
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        if ($start_page > 1): ?>
                        <span class="px-3 py-2 text-gray-500 dark:text-gray-400">...</span>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?logs_page=<?php echo $i; ?>&logs_per_page=<?php echo $logs_per_page; ?>" class="px-3 py-2 <?php echo $i == $current_page ? 'bg-blue-500 text-white border border-blue-500' : 'bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300'; ?> rounded-lg transition-colors shadow-sm font-medium">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                        <span class="px-3 py-2 text-gray-500 dark:text-gray-400">...</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Next Page -->
                    <?php if ($current_page < $total_pages): ?>
                    <a href="?logs_page=<?php echo $current_page + 1; ?>&logs_per_page=<?php echo $logs_per_page; ?>" class="px-3 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg transition-colors shadow-sm">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Last Page -->
                    <?php if ($current_page < $total_pages): ?>
                    <a href="?logs_page=<?php echo $total_pages; ?>&logs_per_page=<?php echo $logs_per_page; ?>" class="px-3 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg transition-colors shadow-sm">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Schedule Modal -->
    <div id="scheduleModal" class="modal">
        <div class="modal-content">
            <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white" id="modalTitle">Add New Schedule</h3>
                <span class="close">&times;</span>
            </div>
            <form id="scheduleForm" class="p-6">
                <input type="hidden" id="scheduleId" name="id">
                
                <!-- Validation Alert -->
                <div id="validationAlert" class="hidden p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-red-500 mt-0.5 mr-3"></i>
                        <div class="flex-1">
                            <h4 class="text-red-800 dark:text-red-200 font-medium mb-1">Please fix the following errors:</h4>
                            <ul class="text-red-700 dark:text-red-300 text-sm space-y-1" id="validationErrors">
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Relay Selection -->
                    <div>
                        <label for="relayNumber" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Relay <span class="text-red-500">*</span></label>
                        <select id="relayNumber" name="relay_number" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            <option value="">Select Relay</option>
                            <option value="1">Filter (Relay 1)</option>
                            <option value="2">Dispense Water (Relay 2)</option>
                        </select>
                        <div id="relay-error" class="mt-1 text-xs hidden">
                            <i class="fas fa-times-circle text-red-500 mr-1"></i>
                            <span class="text-red-600 dark:text-red-400">Please select a relay</span>
                        </div>
                    </div>

                    <!-- Action -->
                    <div>
                        <label for="action" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Action <span class="text-red-500">*</span></label>
                        <select id="action" name="action" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            <option value="1">Turn ON</option>
                            <option value="0">Turn OFF</option>
                        </select>
                    </div>

                    <!-- Schedule Date -->
                    <div>
                        <label for="scheduleDate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date <span class="text-red-500">*</span></label>
                        <input type="date" id="scheduleDate" name="schedule_date" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                        <div id="date-error" class="mt-1 text-xs hidden">
                            <i class="fas fa-times-circle text-red-500 mr-1"></i>
                            <span class="text-red-600 dark:text-red-400">Please select a valid date</span>
                        </div>
                    </div>

                    <!-- Schedule Time -->
                    <div>
                        <label for="scheduleTime" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Time <span class="text-red-500">*</span></label>
                        <input type="time" id="scheduleTime" name="schedule_time" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                        <div id="time-error" class="mt-1 text-xs hidden">
                            <i class="fas fa-times-circle text-red-500 mr-1"></i>
                            <span class="text-red-600 dark:text-red-400">Please select a valid time</span>
                        </div>
                    </div>

                    <!-- Frequency -->
                    <div>
                        <label for="frequency" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Frequency</label>
                        <select id="frequency" name="frequency" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            <option value="once">Once</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>

                    <!-- Active Status -->
                    <div>
                        <label for="isActive" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                        <select id="isActive" name="is_active" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- Description -->
                <div class="mt-6">
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description (Optional)</label>
                    <textarea id="description" name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white" placeholder="Enter a description for this schedule..."></textarea>
                </div>

                <div class="flex justify-end space-x-3 mt-8">
                    <button type="button" id="cancelBtn" class="px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors">
                        <span id="submitBtnText">Create Schedule</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black bg-opacity-50" id="confirmationOverlay"></div>
        <div class="flex items-center justify-center min-h-screen p-4 relative z-10">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-lg w-full relative z-20">
                <div class="p-8">
                    <!-- Header -->
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center mx-auto mb-4" id="confirmationIcon">
                            <i class="fas fa-trash text-red-500 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2" id="confirmationTitle">Delete Schedule</h3>
                        <p class="text-gray-600 dark:text-gray-400" id="confirmationMessage">Are you sure you want to delete this schedule?</p>
                    </div>
                    
                    <!-- Info Box -->
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6" id="infoBox">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-red-500 mt-0.5 mr-3"></i>
                            <div class="text-sm text-red-800 dark:text-red-200">
                                <p class="font-medium mb-1" id="infoTitle">What happens when you delete a schedule?</p>
                                <ul class="space-y-1 text-xs" id="infoList">
                                    <li>• Schedule will be permanently removed</li>
                                    <li>• Any pending executions will be cancelled</li>
                                    <li>• This action cannot be undone</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex space-x-4">
                        <button id="cancelAction" class="flex-1 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 py-3 px-4 rounded-lg transition-colors font-medium">
                            Cancel
                        </button>
                        <button id="confirmAction" class="flex-1 bg-red-500 hover:bg-red-600 text-white py-3 px-4 rounded-lg transition-colors font-medium">
                            Delete Schedule
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Modal functionality
        const modal = document.getElementById('scheduleModal');
        const addScheduleBtn = document.getElementById('addScheduleBtn');
        const createFirstScheduleBtn = document.getElementById('createFirstSchedule');
        const closeBtn = document.querySelector('.close');
        const cancelBtn = document.getElementById('cancelBtn');
        const scheduleForm = document.getElementById('scheduleForm');
        const modalTitle = document.getElementById('modalTitle');
        const submitBtnText = document.getElementById('submitBtnText');
        const scheduleIdInput = document.getElementById('scheduleId');

        // Open modal for new schedule
        function openModal() {
            modal.style.display = 'block';
            modalTitle.textContent = 'Add New Schedule';
            submitBtnText.textContent = 'Create Schedule';
            scheduleForm.reset();
            scheduleIdInput.value = '';
            
            // Set default date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('scheduleDate').value = today;
        }

        // Close modal
        function closeModal() {
            modal.style.display = 'none';
        }

        // Event listeners
        addScheduleBtn.addEventListener('click', openModal);
        if (createFirstScheduleBtn) {
            createFirstScheduleBtn.addEventListener('click', openModal);
        }
        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);

        // Close modal when clicking outside
        window.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        // Form validation and submission
        function validateForm() {
            const errors = [];
            const validationAlert = document.getElementById('validationAlert');
            const validationErrors = document.getElementById('validationErrors');
            
            // Clear previous errors
            validationAlert.classList.add('hidden');
            validationErrors.innerHTML = '';
            
            // Hide all individual error messages
            document.querySelectorAll('[id$="-error"]').forEach(el => el.classList.add('hidden'));
            
            // Validate relay selection
            const relayNumber = document.getElementById('relayNumber').value;
            if (!relayNumber) {
                errors.push('Please select a relay');
                document.getElementById('relay-error').classList.remove('hidden');
            }
            
            // Validate date
            const scheduleDate = document.getElementById('scheduleDate').value;
            if (!scheduleDate) {
                errors.push('Please select a date');
                document.getElementById('date-error').classList.remove('hidden');
            } else {
                const selectedDate = new Date(scheduleDate);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (selectedDate < today) {
                    errors.push('Schedule date cannot be in the past');
                    document.getElementById('date-error').classList.remove('hidden');
                    document.getElementById('date-error').innerHTML = '<i class="fas fa-times-circle text-red-500 mr-1"></i><span class="text-red-600 dark:text-red-400">Schedule date cannot be in the past</span>';
                }
            }
            
            // Validate time
            const scheduleTime = document.getElementById('scheduleTime').value;
            if (!scheduleTime) {
                errors.push('Please select a time');
                document.getElementById('time-error').classList.remove('hidden');
            }
            
            // Validate date and time combination for past schedules
            if (scheduleDate && scheduleTime) {
                const scheduleDateTime = new Date(scheduleDate + 'T' + scheduleTime);
                const now = new Date();
                
                if (scheduleDateTime <= now) {
                    errors.push('Schedule date and time cannot be in the past');
                    document.getElementById('date-error').classList.remove('hidden');
                    document.getElementById('date-error').innerHTML = '<i class="fas fa-times-circle text-red-500 mr-1"></i><span class="text-red-600 dark:text-red-400">Schedule date and time cannot be in the past</span>';
                }
            }
            
            // Show errors if any
            if (errors.length > 0) {
                validationErrors.innerHTML = errors.map(error => `<li>${error}</li>`).join('');
                validationAlert.classList.remove('hidden');
                return false;
            }
            
            return true;
        }

        // Form submission
        scheduleForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!validateForm()) {
                return;
            }
            
            const formData = new FormData(scheduleForm);
            const isEdit = scheduleIdInput.value !== '';
            
            fetch('../../api/schedule_control.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    closeModal();
                    location.reload(); // Refresh page to show new schedule
                } else {
                    // Show validation errors from server
                    const validationAlert = document.getElementById('validationAlert');
                    const validationErrors = document.getElementById('validationErrors');
                    validationErrors.innerHTML = `<li>${data.error}</li>`;
                    validationAlert.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const validationAlert = document.getElementById('validationAlert');
                const validationErrors = document.getElementById('validationErrors');
                validationErrors.innerHTML = '<li>An error occurred while saving the schedule</li>';
                validationAlert.classList.remove('hidden');
            });
        });

        // Edit schedule
        function editSchedule(id) {
            // Fetch schedule data and populate form
            fetch(`../../api/schedule_control.php?id=${id}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const schedule = data.schedule;
                        scheduleIdInput.value = schedule.id;
                        document.getElementById('relayNumber').value = schedule.relay_number;
                        document.getElementById('action').value = schedule.action;
                        document.getElementById('scheduleDate').value = schedule.schedule_date;
                        document.getElementById('scheduleTime').value = schedule.schedule_time;
                        document.getElementById('frequency').value = schedule.frequency;
                        document.getElementById('isActive').value = schedule.is_active;
                        document.getElementById('description').value = schedule.description || '';
                        
                        modalTitle.textContent = 'Edit Schedule';
                        submitBtnText.textContent = 'Update Schedule';
                        modal.style.display = 'block';
                    } else {
                        alert('Error loading schedule: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while loading the schedule.');
                });
        }

        // Confirmation modal functionality
        const confirmationModal = document.getElementById('confirmationModal');
        const confirmationOverlay = document.getElementById('confirmationOverlay');
        const cancelAction = document.getElementById('cancelAction');
        const confirmAction = document.getElementById('confirmAction');
        let currentScheduleId = null;
        let currentScheduleName = null;

        function showConfirmationModal(scheduleId, scheduleName) {
            currentScheduleId = scheduleId;
            currentScheduleName = scheduleName;
            
            const confirmationTitle = document.getElementById('confirmationTitle');
            const confirmationMessage = document.getElementById('confirmationMessage');
            
            confirmationTitle.textContent = 'Delete Schedule';
            confirmationMessage.textContent = `Are you sure you want to delete the schedule "${scheduleName}"?`;
            
            confirmationModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function hideConfirmationModal() {
            confirmationModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
            currentScheduleId = null;
            currentScheduleName = null;
        }

        // Confirmation modal event listeners
        cancelAction.addEventListener('click', hideConfirmationModal);
        confirmationOverlay.addEventListener('click', hideConfirmationModal);

        // Delete schedule with confirmation modal
        function deleteSchedule(id, name) {
            showConfirmationModal(id, name);
        }

        // Handle confirmation action
        confirmAction.addEventListener('click', function() {
            if (currentScheduleId) {
                const formData = new FormData();
                formData.append('_method', 'DELETE');
                formData.append('id', currentScheduleId);
                
                fetch('../../api/schedule_control.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (parseError) {
                            console.error('JSON parse error:', parseError);
                            throw new Error('Invalid JSON response from server');
                        }
                    });
                })
                .then(data => {
                    if (data.success) {
                        hideConfirmationModal();
                        location.reload();
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the schedule: ' + error.message);
                });
            }
        });

        // Bulk delete
        document.getElementById('bulkDelete').addEventListener('click', function() {
            const selectedCheckboxes = document.querySelectorAll('.schedule-checkbox:checked');
            if (selectedCheckboxes.length === 0) {
                alert('Please select schedules to delete.');
                return;
            }
            
            if (confirm(`Are you sure you want to delete ${selectedCheckboxes.length} schedule(s)?`)) {
                const ids = Array.from(selectedCheckboxes).map(cb => cb.value);
                
                const formData = new FormData();
                formData.append('_method', 'DELETE');
                formData.append('ids', JSON.stringify(ids));
                
                fetch('../../api/schedule_control.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the schedules.');
                });
            }
        });

        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.schedule-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Refresh schedules
        document.getElementById('refreshSchedules').addEventListener('click', function() {
            location.reload();
        });

        // Dark mode toggle
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;

        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }

        themeToggle.addEventListener('click', () => {
            html.classList.toggle('dark');
            localStorage.theme = html.classList.contains('dark') ? 'dark' : 'light';
        });

        // Update statistics
        function updateStats() {
            try {
                // Get schedules data from PHP variables directly
                let schedules = window.schedulesData || [];
                
                // If no data available, try to fetch from API
                if (!schedules || schedules.length === 0) {
                    fetch('../../api/schedule_control.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.schedules) {
                                updateStatsWithData(data.schedules);
                            }
                        })
                        .catch(error => {
                            console.error('Failed to fetch schedules:', error);
                            setDefaultStats();
                        });
                    return;
                }
                
                updateStatsWithData(schedules);
            } catch (error) {
                console.error('Error updating stats:', error);
                setDefaultStats();
            }
        }
        
        function updateStatsWithData(schedules) {
            try {
                const activeSchedules = schedules.filter(s => s.is_active == 1).length;
                const today = new Date().toISOString().split('T')[0];
                const todayTasks = schedules.filter(s => s.schedule_date === today).length;
                
                document.getElementById('activeSchedules').textContent = activeSchedules;
                document.getElementById('todayTasks').textContent = todayTasks;
                
                // Find next execution
                const now = new Date();
                const futureSchedules = schedules.filter(s => {
                    try {
                        const scheduleTime = new Date(s.schedule_date + ' ' + s.schedule_time);
                        return scheduleTime > now && s.is_active == 1;
                    } catch (e) {
                        return false;
                    }
                }).sort((a, b) => {
                    try {
                        return new Date(a.schedule_date + ' ' + a.schedule_time) - new Date(b.schedule_date + ' ' + b.schedule_time);
                    } catch (e) {
                        return 0;
                    }
                });
                
                if (futureSchedules.length > 0) {
                    const nextSchedule = futureSchedules[0];
                    const nextTime = new Date(nextSchedule.schedule_date + ' ' + nextSchedule.schedule_time);
                    document.getElementById('nextExecution').textContent = nextTime.toLocaleDateString() + ' ' + nextTime.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
                } else {
                    document.getElementById('nextExecution').textContent = 'No pending tasks';
                }
            } catch (error) {
                console.error('Error processing schedule data:', error);
                setDefaultStats();
            }
        }
        
        function setDefaultStats() {
            document.getElementById('activeSchedules').textContent = '0';
            document.getElementById('todayTasks').textContent = '0';
            document.getElementById('nextExecution').textContent = 'No data';
        }

        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                timeZone: 'Asia/Manila',
                hour12: false 
            });
            const dateString = now.toLocaleDateString('en-US', { 
                timeZone: 'Asia/Manila',
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
            
            document.getElementById('currentTime').textContent = timeString;
            document.getElementById('currentDate').textContent = dateString;
        }

        // Update time every second
        updateTime();
        setInterval(updateTime, 1000);

        // Manual schedule execution for testing
        function executeSchedulesNow() {
            fetch('../../api/execute_schedules.php')
                .then(response => response.text())
                .then(data => {
                    console.log('Schedule execution result:', data);
                    alert('Schedule execution completed. Check console for details.');
                })
                .catch(error => {
                    console.error('Error executing schedules:', error);
                    alert('Error executing schedules. Check console for details.');
                });
        }

        // Add manual execution button to the page
        const manualExecuteBtn = document.createElement('button');
        manualExecuteBtn.innerHTML = '<i class="fas fa-play mr-2"></i>Execute Now';
        manualExecuteBtn.className = 'px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors text-sm';
        manualExecuteBtn.onclick = executeSchedulesNow;
        
        // Add it next to the refresh button
        const refreshBtn = document.getElementById('refreshSchedules');
        if (refreshBtn && refreshBtn.parentNode) {
            refreshBtn.parentNode.insertBefore(manualExecuteBtn, refreshBtn.nextSibling);
        }

        // Try to update stats, but don't let it break the page
        try {
            updateStats();
        } catch (error) {
            console.error('Failed to update stats:', error);
            // Set default values
            document.getElementById('activeSchedules').textContent = '0';
            document.getElementById('todayTasks').textContent = '0';
            document.getElementById('nextExecution').textContent = 'No data';
        }

        // Real-time status updates
        function updateScheduleStatus() {
            fetch('../../api/schedule_control.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.schedules) {
                        updateScheduleTable(data.schedules);
                        updateStatsWithData(data.schedules);
                    }
                })
                .catch(error => {
                    console.error('Failed to update schedule status:', error);
                });
        }

        function updateScheduleTable(schedules) {
            const tbody = document.getElementById('scheduleTableBody');
            if (!tbody) return;

            // Update each row with new status
            schedules.forEach(schedule => {
                const row = tbody.querySelector(`tr[data-schedule-id="${schedule.id}"]`);
                if (row) {
                    // Update status badge
                    const statusCell = row.querySelector('.status-badge');
                    if (statusCell) {
                        let status = 'pending';
                        let statusClass = 'status-pending';
                        
                        if (schedule.is_active == 0) {
                            status = 'inactive';
                            statusClass = 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                        } else if (schedule.last_executed !== null) {
                            status = 'completed';
                            statusClass = 'status-completed';
                        } else if (new Date(schedule.schedule_date + ' ' + schedule.schedule_time) < new Date()) {
                            status = 'overdue';
                            statusClass = 'status-overdue';
                        } else {
                            status = 'active';
                            statusClass = 'status-active';
                        }
                        
                        statusCell.className = `status-badge ${statusClass}`;
                        statusCell.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                    }

                    // Update last executed time
                    const lastExecutedCell = row.querySelector('td:nth-child(7) div');
                    if (lastExecutedCell) {
                        lastExecutedCell.textContent = schedule.last_executed ? 
                            new Date(schedule.last_executed).toLocaleDateString('en-US', {
                                month: 'short',
                                day: 'numeric'
                            }) + ' ' + new Date(schedule.last_executed).toLocaleTimeString([], {
                                hour: '2-digit',
                                minute: '2-digit'
                            }) : 'Never';
                    }
                }
            });
        }

        // Add data-schedule-id attributes to table rows for easier updates
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('#scheduleTableBody tr');
            rows.forEach(row => {
                const checkbox = row.querySelector('.schedule-checkbox');
                if (checkbox) {
                    row.setAttribute('data-schedule-id', checkbox.value);
                }
            });
        });

        // Auto-refresh schedule status every 30 seconds
        setInterval(updateScheduleStatus, 30000);

        // Also update when the page becomes visible (user switches back to tab)
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                updateScheduleStatus();
            }
        });

        // Refresh logs button
        document.getElementById('refreshLogs').addEventListener('click', function() {
            location.reload();
        });

        // Clear logs button
        document.getElementById('clearLogs').addEventListener('click', function() {
            if (confirm('Are you sure you want to clear all execution logs? This action cannot be undone.')) {
                fetch('../../api/clear_schedule_logs.php', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while clearing logs.');
                });
            }
        });

        // Page size selector functionality
        document.getElementById('pageSize').addEventListener('change', function() {
            const pageSize = this.value;
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('logs_per_page', pageSize);
            currentUrl.searchParams.delete('logs_page'); // Reset to first page
            window.location.href = currentUrl.toString();
        });

        // Add smooth scrolling to pagination links
        document.querySelectorAll('a[href*="logs_page"]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const href = this.getAttribute('href');
                window.location.href = href;
                // Smooth scroll to top of logs section
                document.querySelector('.bg-white.dark\\:bg-gray-800.rounded-2xl.shadow-lg.p-8').scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            });
        });
    </script>
</body>
</html>
