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
        window.schedulesData = <?php echo json_encode($schedules ?? [], JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES); ?>;
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
                                            <button onclick="deleteSchedule(<?php echo $schedule['id']; ?>)" class="p-2 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors">
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
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Relay Selection -->
                    <div>
                        <label for="relayNumber" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Relay</label>
                        <select id="relayNumber" name="relay_number" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            <option value="">Select Relay</option>
                            <option value="1">Filter (Relay 1)</option>
                            <option value="2">Dispense Water (Relay 2)</option>
                        </select>
                    </div>

                    <!-- Action -->
                    <div>
                        <label for="action" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Action</label>
                        <select id="action" name="action" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            <option value="1">Turn ON</option>
                            <option value="0">Turn OFF</option>
                        </select>
                    </div>

                    <!-- Schedule Date -->
                    <div>
                        <label for="scheduleDate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date</label>
                        <input type="date" id="scheduleDate" name="schedule_date" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Schedule Time -->
                    <div>
                        <label for="scheduleTime" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Time</label>
                        <input type="time" id="scheduleTime" name="schedule_time" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
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

        // Form submission
        scheduleForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(scheduleForm);
            const isEdit = scheduleIdInput.value !== '';
            
            // Debug: Log form data
            console.log('Form submission - isEdit:', isEdit);
            for (let [key, value] of formData.entries()) {
                console.log('Form data:', key, '=', value);
            }
            
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
                console.log('API response:', data);
                if (data.success) {
                    closeModal();
                    location.reload(); // Refresh page to show new schedule
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the schedule.');
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

        // Delete schedule
        function deleteSchedule(id) {
            if (confirm('Are you sure you want to delete this schedule?')) {
                const formData = new FormData();
                formData.append('_method', 'DELETE');
                formData.append('id', id);
                
                fetch('../../api/schedule_control.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    // Get the raw text first to debug
                    return response.text().then(text => {
                        console.log('Raw response:', text);
                        console.log('Response length:', text.length);
                        
                        // Try to parse as JSON
                        try {
                            return JSON.parse(text);
                        } catch (parseError) {
                            console.error('JSON parse error:', parseError);
                            console.error('Failed to parse:', text);
                            throw new Error('Invalid JSON response from server');
                        }
                    });
                })
                .then(data => {
                    console.log('Parsed data:', data);
                    if (data.success) {
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
        }

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
                    document.getElementById('nextExecution').textContent = nextTime.toLocaleDateString() + ' ' + nextTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
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
    </script>
</body>
</html>
