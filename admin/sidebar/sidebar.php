<?php


// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Check current path for better active state detection
$current_path = $_SERVER['REQUEST_URI'];

// Check if we're in specific directories
$is_dashboard_page = strpos($current_path, '/dashboard/') !== false;
$is_monitor_page = strpos($current_path, '/monitor/') !== false;
$is_analytics_page = strpos($current_path, '/analytics/') !== false;
$is_controls_page = strpos($current_path, '/controls/') !== false;
$is_schedule_page = strpos($current_path, '/schedule/') !== false;
$is_alerts_page = strpos($current_path, '/alerts/') !== false;
$is_user_page = strpos($current_path, '/user/') !== false;
$is_actlogs_page = strpos($current_path, '/actlogs/') !== false;

// Get current user information
$current_user_name = 'Admin User';
$current_user_role = 'Administrator';

if (isset($_SESSION['user_id'])) {
    try {
        require_once '../../config/database.php';
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
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
}
// Normalize role and compute permissions
$normalized_role = strtolower($current_user_role);
$is_staff = ($normalized_role === 'staff');
?>
<div class="fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-gray-800 shadow-lg transform transition-transform duration-300 ease-in-out" id="sidebar">
    <!-- Sidebar Header -->
    <div class="relative flex items-center justify-between h-16 px-6 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center">
            <i class="fas fa-water text-blue-500 text-xl mr-3"></i>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Water Quality</h2>
        </div>
        <button id="sidebarClose" class="lg:hidden absolute right-3 top-3 p-2 rounded-md text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Navigation Menu -->
    <nav class="mt-6 px-4">
        <div class="space-y-2">
            <!-- Dashboard -->
            <a href="../dashboard" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $is_dashboard_page ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white'; ?>">
                <i class="fas fa-tachometer-alt w-5 h-5 mr-3"></i>
                <span>Dashboard</span>
            </a>

            <!-- Monitor -->
            <a href="../monitor" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $is_monitor_page ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white'; ?>">
                <i class="fas fa-desktop w-5 h-5 mr-3"></i>
                <span>Monitor</span>
            </a>

            <!-- Data Overview -->
            <?php if (!$is_staff): ?>
            <a href="../analytics" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $is_analytics_page ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white'; ?>">
                <i class="fas fa-chart-line w-5 h-5 mr-3"></i>
                <span>Data Overview</span>
            </a>
            <?php endif; ?>

            <!-- Controls -->
            <a href="../controls" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $is_controls_page ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white'; ?>">
                <i class="fas fa-sliders-h w-5 h-5 mr-3"></i>
                <span>Controls</span>
            </a>

            <!-- Schedule -->
            <a href="../schedule" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $is_schedule_page ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white'; ?>">
                <i class="fas fa-calendar-alt w-5 h-5 mr-3"></i>
                <span>Schedule</span>
            </a>

            <!-- Alerts -->
            <a href="../alerts" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $is_alerts_page ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white'; ?>">
                <i class="fas fa-bell w-5 h-5 mr-3"></i>
                <span>Alerts</span>
            </a>

            <!-- User -->
            <?php if (!$is_staff): ?>
            <a href="../user" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $is_user_page ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white'; ?>">
                <i class="fas fa-user w-5 h-5 mr-3"></i>
                <span>User</span>
            </a>
            <?php endif; ?>

            <!-- Activity Logs -->
            <?php if (!$is_staff): ?>
            <a href="../actlogs" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $is_actlogs_page ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white'; ?>">
                <i class="fas fa-history w-5 h-5 mr-3"></i>
                <span>Activity Logs</span>
            </a>
            <?php endif; ?>

        </div>

        <!-- Divider -->
        <div class="border-t border-gray-200 dark:border-gray-700 my-6"></div>

        <!-- Logout -->
        <a href="../../login/logout.php" class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
            <i class="fas fa-sign-out-alt w-5 h-5 mr-3"></i>
            <span>Logout</span>
        </a>
    </nav>

    <!-- Sidebar Footer -->
    <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-200 dark:border-gray-700">
        <div class="flex items-center">
            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                <i class="fas fa-user text-white text-sm"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-gray-800 dark:text-white"><?php echo htmlspecialchars($current_user_name); ?></p>
                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($current_user_role); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Overlay -->
<div id="sidebarOverlay" class="fixed inset-0 z-40 bg-black bg-opacity-50 lg:hidden hidden"></div>

<!-- Top-right Controls (mobile) -->
<div class="fixed top-4 right-4 z-50 lg:hidden flex items-center gap-2">
    <!-- Time Badge -->
    <span id="topRightTime" class="px-2 py-1 rounded-md text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 shadow">
        --:--:--
    </span>
    <!-- Theme Toggle -->
    <button id="themeToggleTop" class="p-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
        <i class="fas fa-sun text-yellow-500 dark:hidden"></i>
        <i class="fas fa-moon text-blue-300 hidden dark:block"></i>
    </button>
    <!-- Sidebar Toggle Button (mobile) -->
    <button id="sidebarToggle" class="p-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
        <i class="fas fa-bars"></i>
    </button>
    
</div>

<script>
// Sidebar functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const themeToggleTop = document.getElementById('themeToggleTop');
    const topRightTime = document.getElementById('topRightTime');

    // Toggle sidebar on mobile
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.remove('-translate-x-full');
        sidebarOverlay.classList.remove('hidden');
    });

    // Close sidebar
    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        sidebarOverlay.classList.add('hidden');
    }

    sidebarClose.addEventListener('click', closeSidebar);
    sidebarOverlay.addEventListener('click', closeSidebar);

    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) { // lg breakpoint
            sidebar.classList.remove('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
        } else {
            sidebar.classList.add('-translate-x-full');
        }
    });

    // Initialize sidebar state based on screen size
    if (window.innerWidth < 1024) {
        sidebar.classList.add('-translate-x-full');
    }

    // Theme handling (sync with existing preference)
    try {
        const html = document.documentElement;
        const saved = localStorage.getItem('theme');
        if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }
        if (themeToggleTop) {
            themeToggleTop.addEventListener('click', () => {
                html.classList.toggle('dark');
                localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
            });
        }
    } catch (_) {}

    // Clock in top-right controls
    function updateTopRightTime() {
        if (!topRightTime) return;
        const now = new Date();
        topRightTime.textContent = now.toLocaleTimeString();
    }
    updateTopRightTime();
    setInterval(updateTopRightTime, 1000);
});
</script> 