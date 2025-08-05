<?php
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login/index.php');
    exit;
}

require_once '../../config/database.php';

// Pagination settings
$logs_per_page = 20;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $logs_per_page;

// Filter settings
$filter_action = isset($_GET['action']) ? $_GET['action'] : '';
$filter_user = isset($_GET['user']) ? $_GET['user'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($filter_action) {
    $where_conditions[] = "action_type = ?";
    $params[] = $filter_action;
    $param_types .= 's';
}

if ($filter_user) {
    $where_conditions[] = "performed_by LIKE ?";
    $params[] = "%$filter_user%";
    $param_types .= 's';
}

if ($filter_date_from) {
    $where_conditions[] = "DATE(timestamp) >= ?";
    $params[] = $filter_date_from;
    $param_types .= 's';
}

if ($filter_date_to) {
    $where_conditions[] = "DATE(timestamp) <= ?";
    $params[] = $filter_date_to;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $count_query = "SELECT COUNT(*) as total FROM activity_logs $where_clause";
    if (!empty($params)) {
        $count_stmt = $conn->prepare($count_query);
        $count_stmt->bind_param($param_types, ...$params);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
    } else {
        $count_result = $conn->query($count_query);
    }
    $total_logs = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_logs / $logs_per_page);
    
    // Get activity logs with pagination
    $logs_query = "SELECT al.*, u.first_name, u.last_name 
                   FROM activity_logs al 
                   LEFT JOIN users u ON al.user_id = u.id 
                   $where_clause 
                   ORDER BY al.timestamp DESC 
                   LIMIT ? OFFSET ?";
    
    $params[] = $logs_per_page;
    $params[] = $offset;
    $param_types .= 'ii';
    
    $logs_stmt = $conn->prepare($logs_query);
    $logs_stmt->bind_param($param_types, ...$params);
    $logs_stmt->execute();
    $logs_result = $logs_stmt->get_result();
    
    $activity_logs = [];
    while ($row = $logs_result->fetch_assoc()) {
        $activity_logs[] = $row;
    }
    
    // Get unique action types for filter dropdown
    $action_types_result = $conn->query("SELECT DISTINCT action_type FROM activity_logs ORDER BY action_type");
    $action_types = [];
    while ($row = $action_types_result->fetch_assoc()) {
        $action_types[] = $row['action_type'];
    }
    
} catch (Exception $e) {
    $activity_logs = [];
    $total_pages = 1;
    $total_logs = 0;
    $action_types = [];
}

// Get current user info for sidebar
$current_user_name = 'Admin User';
$current_user_role = 'Administrator';

try {
    $stmt = $conn->prepare("SELECT first_name, last_name, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        $current_user_name = $row['first_name'] . ' ' . $row['last_name'];
        $current_user_role = ucfirst($row['role']);
    }
} catch (Exception $e) {
    // Keep default values if database error occurs
}

function getActionIcon($action_type) {
    switch ($action_type) {
        case 'user_created':
            return 'fas fa-user-plus text-green-500';
        case 'user_updated':
            return 'fas fa-user-edit text-blue-500';
        case 'user_archived':
            return 'fas fa-user-times text-orange-500';
        case 'user_activated':
            return 'fas fa-user-check text-green-500';
        case 'login':
            return 'fas fa-sign-in-alt text-blue-500';
        case 'logout':
            return 'fas fa-sign-out-alt text-gray-500';
        case 'relay_control':
            return 'fas fa-toggle-on text-purple-500';
        case 'system_config':
            return 'fas fa-cog text-indigo-500';
        default:
            return 'fas fa-info-circle text-gray-500';
    }
}

function getActionColor($action_type) {
    switch ($action_type) {
        case 'user_created':
        case 'user_activated':
            return 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300';
        case 'user_updated':
        case 'login':
        case 'relay_control':
            return 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300';
        case 'user_archived':
            return 'bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-300';
        case 'logout':
            return 'bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300';
        case 'system_config':
            return 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300';
        default:
            return 'bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300';
    }
}

function formatActionDescription($action_type, $details) {
    switch ($action_type) {
        case 'user_created':
            return "Created new user: " . htmlspecialchars($details);
        case 'user_updated':
            return "Updated user: " . htmlspecialchars($details);
        case 'user_archived':
            return "Archived user: " . htmlspecialchars($details);
        case 'user_activated':
            return "Activated user: " . htmlspecialchars($details);
        case 'login':
            return "User logged in";
        case 'logout':
            return "User logged out";
        case 'relay_control':
            return "Relay control: " . htmlspecialchars($details);
        case 'system_config':
            return "System configuration: " . htmlspecialchars($details);
        default:
            return htmlspecialchars($details);
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Water Quality Monitor</title>
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
                            bg: '#111827',
                            card: '#1F2937',
                            text: '#F3F4F6',
                            muted: '#9CA3AF'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #f6f8fc 0%, #ffffff 100%);
        }
        .dark .gradient-bg {
            background: linear-gradient(135deg, #111827 0%, #1F2937 100%);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen transition-colors duration-200">
    <!-- Include Sidebar -->
    <?php include '../sidebar/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64">
        <div class="container mx-auto px-4 py-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
                        <i class="fas fa-history text-blue-500 mr-2"></i>
                        Activity Logs
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">Track all system activities and user actions</p>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        <i class="fas fa-clock mr-1"></i>
                        <span id="currentTime">--:--:--</span>
                    </span>
                    <button id="themeToggle" class="p-2 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                        <i class="fas fa-sun text-yellow-500 dark:hidden"></i>
                        <i class="fas fa-moon text-blue-300 hidden dark:block"></i>
                    </button>
                </div>
            </div>



            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 mb-8">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">
                    <i class="fas fa-filter text-blue-500 mr-2"></i>
                    Filters
                </h3>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Action Type</label>
                        <select name="action" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Actions</option>
                            <?php foreach ($action_types as $action): ?>
                                <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $filter_action === $action ? 'selected' : ''; ?>>
                                    <?php echo ucwords(str_replace('_', ' ', $action)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">User</label>
                        <input type="text" name="user" value="<?php echo htmlspecialchars($filter_user); ?>" placeholder="Search by user..." class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date From</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                    </div>
                </form>
                <?php if ($filter_action || $filter_user || $filter_date_from || $filter_date_to): ?>
                    <div class="mt-4">
                        <a href="actlogs.php" class="text-blue-500 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-300 text-sm">
                            <i class="fas fa-times mr-1"></i>Clear Filters
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Activity Logs Table -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
                        <i class="fas fa-list text-blue-500 mr-2"></i>
                        Recent Activities
                    </h3>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Showing <?php echo count($activity_logs); ?> of <?php echo number_format($total_logs); ?> activities
                    </div>
                </div>

                <?php if (empty($activity_logs)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-500 dark:text-gray-400">No activity logs found</p>
                        <?php if ($filter_action || $filter_user || $filter_date_from || $filter_date_to): ?>
                            <p class="text-sm text-gray-400 mt-2">Try adjusting your filters</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Action
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        User
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Message
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Details
                                    </th>

                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Timestamp
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($activity_logs as $log): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <i class="<?php echo getActionIcon($log['action_type']); ?> mr-3"></i>
                                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo ucwords(str_replace('_', ' ', $log['action_type'])); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($log['performed_by']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($log['message']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($log['details']); ?>
                                            </div>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white">
                                                <?php echo date('M j, Y', strtotime($log['timestamp'])); ?>
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo date('g:i A', strtotime($log['timestamp'])); ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="flex items-center justify-between mt-6">
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                            </div>
                            <div class="flex space-x-2">
                                <?php if ($current_page > 1): ?>
                                    <a href="?page=<?php echo $current_page - 1; ?>&action=<?php echo urlencode($filter_action); ?>&user=<?php echo urlencode($filter_user); ?>&date_from=<?php echo urlencode($filter_date_from); ?>&date_to=<?php echo urlencode($filter_date_to); ?>" class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                        <i class="fas fa-chevron-left mr-1"></i>Previous
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                    <a href="?page=<?php echo $i; ?>&action=<?php echo urlencode($filter_action); ?>&user=<?php echo urlencode($filter_user); ?>&date_from=<?php echo urlencode($filter_date_from); ?>&date_to=<?php echo urlencode($filter_date_to); ?>" class="px-3 py-2 text-sm rounded-lg transition-colors <?php echo $i === $current_page ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($current_page < $total_pages): ?>
                                    <a href="?page=<?php echo $current_page + 1; ?>&action=<?php echo urlencode($filter_action); ?>&user=<?php echo urlencode($filter_user); ?>&date_from=<?php echo urlencode($filter_date_from); ?>&date_to=<?php echo urlencode($filter_date_to); ?>" class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                        Next<i class="fas fa-chevron-right ml-1"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Dark mode toggle functionality
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;

        // Check for saved theme preference
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }

        // Toggle theme
        themeToggle.addEventListener('click', () => {
            html.classList.toggle('dark');
            localStorage.theme = html.classList.contains('dark') ? 'dark' : 'light';
        });

        // Update current time
        function updateCurrentTime() {
            const now = new Date();
            document.getElementById('currentTime').textContent = now.toLocaleTimeString();
        }
        setInterval(updateCurrentTime, 1000);
        updateCurrentTime();
    </script>
</body>
</html>
