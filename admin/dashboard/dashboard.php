<?php
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login/index.php');
    exit;
}

require_once '../../config/database.php';

// Get current user information
$currentUser = null;
if (isset($_SESSION['user_id'])) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT username, full_name, email FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $currentUser = $result->fetch_assoc();
        }
    } catch (Exception $e) {
        // User info not critical, continue without it
    }
}

// Handle POST request from Arduino
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $turbidity = isset($_POST['turbidity']) ? floatval($_POST['turbidity']) : null;
    $tds = isset($_POST['tds']) ? floatval($_POST['tds']) : null;

    if ($turbidity !== null && $tds !== null) {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("INSERT INTO water_readings (turbidity, tds) VALUES (?, ?)");
            $stmt->bind_param("dd", $turbidity, $tds);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Failed to save data']);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
}

    // Get 4th-to-last readings (skip the latest 3)
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        $result = $conn->query("SELECT * FROM water_readings ORDER BY reading_time DESC LIMIT 1 OFFSET 3");
        $readings = $result->fetch_all(MYSQLI_ASSOC);
        
        // Get data for charts (excluding the latest 3 readings)
        $chartResult = $conn->query("SELECT reading_time, turbidity, tds FROM water_readings ORDER BY reading_time DESC LIMIT 1 OFFSET 3, 24");
        $chartData = $chartResult->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        $readings = [];
        $chartData = [];
    }
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Quality Monitor</title>
    <link rel="icon" type="image/png" href="../../assets/images/icons/icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        // Configure Tailwind dark mode
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
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #f6f8fc 0%, #ffffff 100%);
        }
        .dark .gradient-bg {
            background: linear-gradient(135deg, #111827 0%, #1F2937 100%);
        }
        .dark .card-hover:hover {
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
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
                    <i class="fas fa-water text-blue-500 mr-2"></i>
                    Water Quality Monitor
                </h1>
                <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">Real-time water quality monitoring system</p>
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
        
        <!-- Sensor Cards Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Turbidity Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 card-hover">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                            <i class="fas fa-filter text-blue-500 dark:text-blue-400 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Turbidity</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Water Clarity</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold text-gray-800 dark:text-white" id="turbidityValue">--</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">NTU</div>
                        <div class="text-xs text-blue-500 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 px-2 py-1 rounded-full inline-block" id="turbidityPercent">
                            <i class="fas fa-percentage mr-1"></i>--%
                        </div>
                    </div>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400" id="turbidityTime">
                    <i class="fas fa-clock mr-1"></i>Last updated: --
                </div>
            </div>

            <!-- TDS Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 card-hover">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-900 flex items-center justify-center">
                            <i class="fas fa-flask text-emerald-500 dark:text-emerald-400 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">TDS</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Dissolved Solids</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold text-gray-800 dark:text-white" id="tdsValue">--</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">%</div>
                        <div class="text-xs text-gray-400 dark:text-gray-500 mt-1" id="tdsRaw">Raw: -- ppm</div>
                    </div>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400" id="tdsTime">
                    <i class="fas fa-clock mr-1"></i>Last updated: --
                </div>
            </div>

            <!-- pH Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 card-hover">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-purple-100 dark:bg-purple-900 flex items-center justify-center">
                            <i class="fas fa-vial text-purple-500 dark:text-purple-400 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">pH Level</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Acidity/Alkalinity</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold text-gray-800 dark:text-white" id="phValue">--</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">pH</div>
                    </div>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400" id="phTime">
                    <i class="fas fa-clock mr-1"></i>Last updated: --
                </div>
            </div>

            <!-- Temperature Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 card-hover">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center">
                            <i class="fas fa-thermometer-half text-red-500 dark:text-red-400 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Temperature</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Water Temperature</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold text-gray-800 dark:text-white" id="temperatureValue">--</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">°C</div>
                    </div>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400" id="temperatureTime">
                    <i class="fas fa-clock mr-1"></i>Last updated: --
                </div>
            </div>
        </div>

        <!-- Water Quality Alerts -->
        <div class="mb-8">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h5 class="text-lg font-semibold text-gray-800 dark:text-white">
                        <i class="fas fa-exclamation-triangle mr-2 text-yellow-500"></i>
                        Water Quality Status
                    </h5>
                    <div class="flex items-center space-x-2">
                        <span id="unacknowledgedCount" class="hidden px-3 py-1 text-xs font-medium bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-700 rounded-full">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <span id="unackCount">0</span> Unacknowledged
                        </span>
                    </div>
                </div>
                <div id="waterQualityAlerts" class="space-y-4">
                    <!-- Alerts will be dynamically inserted here -->
                </div>
            </div>
        </div>

        <!-- Acknowledgment Reports -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <h5 class="text-lg font-semibold text-gray-800 dark:text-white">
                    <i class="fas fa-clipboard-check mr-2 text-amber-500"></i>
                    Acknowledgment Reports
                </h5>
                <div class="flex items-center space-x-6">
                    <!-- Export Button -->
                    <button id="exportAcknowledgmentReports" class="px-4 py-2 bg-green-100 dark:bg-green-900 text-green-600 dark:text-green-300 rounded-lg hover:bg-green-200 dark:hover:bg-green-800 transition-colors">
                        <i class="fas fa-download mr-2"></i>Export
                    </button>
                    <!-- Summary Statistics -->
                    <div class="flex items-center space-x-4">
                        <!-- Total Acknowledged -->
                        <div class="bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 border border-amber-200 dark:border-amber-800 rounded-xl px-4 py-3 shadow-sm">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                    <i class="fas fa-clipboard-check text-amber-600 dark:text-amber-400 text-lg"></i>
                                </div>
                                <div>
                                    <div class="text-xs font-medium text-amber-700 dark:text-amber-300 uppercase tracking-wide">
                                        Total Acknowledged
                                    </div>
                                    <div class="text-2xl font-bold text-amber-900 dark:text-amber-100 transition-all duration-300" id="totalAcknowledged">--</div>
                                </div>
                            </div>
                        </div>

                        <!-- Today's Acknowledged -->
                        <div class="bg-gradient-to-r from-emerald-50 to-green-50 dark:from-emerald-900/20 dark:to-green-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl px-4 py-3 shadow-sm">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                                    <i class="fas fa-calendar-day text-emerald-600 dark:text-emerald-400 text-lg"></i>
                                </div>
                                <div>
                                    <div class="text-xs font-medium text-emerald-700 dark:text-emerald-300 uppercase tracking-wide">
                                        Today's Acknowledged
                                    </div>
                                    <div class="text-2xl font-bold text-emerald-900 dark:text-emerald-100 transition-all duration-300" id="todayAcknowledged">--</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Refresh Button -->
                    <button onclick="refreshAcknowledgmentReports()" class="bg-amber-600 hover:bg-amber-700 dark:bg-amber-700 dark:hover:bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                        <i class="fas fa-sync-alt mr-2"></i>Refresh
                    </button>
                </div>
            </div>
            
            <!-- Total Acknowledgments Count -->
            <div class="mb-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <span class="font-medium">Total Acknowledgments:</span>
                    <span id="totalAcknowledgmentCount" class="ml-2 px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded-full text-xs font-medium">
                        0
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <i class="fas fa-exclamation-triangle mr-1"></i>Alert Type
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <i class="fas fa-tools mr-1"></i>Action Taken
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <i class="fas fa-user mr-1"></i>Responsible Person
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <i class="fas fa-clock mr-1"></i>Acknowledged At
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <i class="fas fa-info-circle mr-1"></i>Details
                            </th>
                        </tr>
                    </thead>
                    <tbody id="acknowledgmentReportsTable" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Loading acknowledgment reports...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <div class="mt-6 flex items-center justify-between">
                <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                    <span id="ackPaginationInfo">Showing 0 to 0 of 0 results</span>
                </div>
                
                <div class="flex items-center space-x-2">
                    <button id="ackPrevPage" class="px-3 py-1 text-sm bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        <i class="fas fa-chevron-left mr-1"></i>Previous
                    </button>
                    
                    <div id="ackPageNumbers" class="flex items-center space-x-1">
                        <!-- Page numbers will be generated here -->
                    </div>
                    
                    <button id="ackNextPage" class="px-3 py-1 text-sm bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        Next<i class="fas fa-chevron-right ml-1"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Chart and Table Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Chart -->
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <h5 class="text-lg font-semibold text-gray-800 dark:text-white">
                        <i class="fas fa-chart-line mr-2 text-blue-500"></i>
                        Historical Data
                    </h5>
                    <div class="flex space-x-2">
                        <button class="px-3 py-1 text-sm bg-blue-50 dark:bg-blue-900 text-blue-600 dark:text-blue-300 rounded-full hover:bg-blue-100 dark:hover:bg-blue-800">
                            <i class="fas fa-clock mr-1"></i>24h
                        </button>
                        <button class="px-3 py-1 text-sm bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-600">
                            <i class="fas fa-calendar mr-1"></i>7d
                        </button>
                    </div>
                </div>
                <div class="h-[400px]">
                    <canvas id="readingsChart"></canvas>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <h5 class="text-lg font-semibold text-gray-800 dark:text-white">
                        <i class="fas fa-table mr-2 text-blue-500"></i>
                        Recent Readings
                    </h5>
                    <button class="text-sm text-blue-500 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-300">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <i class="fas fa-clock mr-1"></i>Time
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <i class="fas fa-filter mr-1"></i>Turb NTU
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <i class="fas fa-flask mr-1"></i>TDS %
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <i class="fas fa-vial mr-1"></i>pH
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <i class="fas fa-thermometer-half mr-1"></i>Temp
                                </th>
                            </tr>
                        </thead>
                        <tbody id="readingsTable" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Relay Control Panel -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-6">
                <h5 class="text-lg font-semibold text-gray-800 dark:text-white">
                    <i class="fas fa-sliders-h mr-2 text-blue-500"></i>
                    Control Panel
                </h5>
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        <i class="fas fa-circle text-green-500 mr-1"></i>System Online
                    </span>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Filter -->
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center">
                            <i class="fas fa-filter text-blue-500 dark:text-blue-400 mr-2"></i>
                            <h6 class="text-sm font-medium text-gray-700 dark:text-gray-300">Filter</h6>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">IN1</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer" data-relay="1" onchange="toggleRelay(this)">
                        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <!-- Dispense Water -->
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center">
                            <i class="fas fa-tint text-blue-500 dark:text-blue-400 mr-2"></i>
                            <h6 class="text-sm font-medium text-gray-700 dark:text-gray-300">Dispense Water</h6>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">IN2</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer" data-relay="2" onchange="toggleRelay(this)">
                        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- Acknowledgment Modal -->
    <div id="acknowledgeModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-6 border border-gray-200 dark:border-gray-700 w-full max-w-md shadow-2xl rounded-xl bg-white dark:bg-gray-800">
            <div class="mt-3">
                <!-- Modal Header -->
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                        <i class="fas fa-shield-alt text-amber-500 mr-3"></i>
                        Acknowledge Alert
                    </h3>
                    <button id="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                
                <!-- Alert Details -->
                <div id="modalAlertDetails" class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                    <!-- Alert details will be inserted here -->
                </div>
                
                <!-- Acknowledgment Form -->
                <form id="acknowledgeForm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Action Taken <span class="text-red-500">*</span>
                        </label>
                        <select id="actionTaken" name="action_taken" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                            <option value="">Select an action...</option>
                            <option value="filter_replacement">Filter Replacement</option>
                            <option value="system_maintenance">System Maintenance</option>
                            <option value="chemical_treatment">Chemical Treatment</option>
                            <option value="system_flush">System Flush</option>
                            <option value="investigation">Under Investigation</option>
                            <option value="manual_intervention">Manual Intervention</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Details <span class="text-red-500">*</span>
                        </label>
                        <textarea id="acknowledgeDetails" name="details" required rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                                  placeholder="Please describe what action was taken to address this alert..."></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Responsible Person
                        </label>
                        <input type="text" id="responsiblePerson" name="responsible_person" 
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                               value="<?php echo isset($currentUser['full_name']) ? htmlspecialchars($currentUser['full_name']) : (isset($currentUser['username']) ? htmlspecialchars($currentUser['username']) : ''); ?>"
                               placeholder="Enter your name or ID">
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="button" id="cancelAcknowledge" 
                                class="px-6 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" id="submitAcknowledge"
                                class="px-6 py-2.5 text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 dark:bg-amber-700 dark:hover:bg-amber-600 rounded-lg transition-colors shadow-sm">
                            <i class="fas fa-shield-alt mr-2"></i>Acknowledge Alert
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let readingsChart = null;

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleTimeString();
        }

        // Function to toggle relay state
        function toggleRelay(checkbox) {
            const relay = checkbox.dataset.relay;
            const state = checkbox.checked ? 1 : 0;
            
            // Show loading state
            checkbox.disabled = true;
            
            // Send command to server
            fetch('../../api/relay_control.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json'
                },
                body: `relay=${relay}&state=${state}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update all relay states based on server response
                    if (data.states) {
                        data.states.forEach(state => {
                            const checkbox = document.querySelector(`input[data-relay="${state.relay_number}"]`);
                            if (checkbox) {
                                checkbox.checked = state.state === 1;
                            }
                        });
                    }
                } else {
                    console.error('Error updating relay state:', data.error);
                    checkbox.checked = !checkbox.checked; // Revert the toggle if there was an error
                }
            })
            .catch(error => {
                console.error('Error:', error);
                checkbox.checked = !checkbox.checked; // Revert the toggle if there was an error
            })
            .finally(() => {
                checkbox.disabled = false; // Re-enable the checkbox
            });
        }

        // Function to fetch current relay states
        function fetchRelayStates() {
            fetch('../../api/relay_control.php', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.states) {
                        data.states.forEach(state => {
                            const checkbox = document.querySelector(`input[data-relay="${state.relay_number}"]`);
                            if (checkbox) {
                                checkbox.checked = state.state === 1;
                            }
                        });
                    }
                })
                .catch(error => console.error('Error fetching relay states:', error));
        }

        function updateData() {
            fetch('../../api/get_readings.php', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }

                    // Update sensor cards
                    const latest = data.latest;
                    if (latest) {
                        // Convert and display percentages
                        const turbidityPercent = convertTurbidityToPercentage(parseFloat(latest.turbidity_ntu));
                        const tdsPercent = convertTDSToPercentage(parseFloat(latest.tds_ppm));
                        
                        document.getElementById('turbidityValue').textContent = parseFloat(latest.turbidity_ntu).toFixed(0);
                        document.getElementById('tdsValue').textContent = tdsPercent.toFixed(1);
                        document.getElementById('phValue').textContent = parseFloat(latest.ph).toFixed(2);
                        document.getElementById('temperatureValue').textContent = parseFloat(latest.temperature).toFixed(2);
                        
                        // Update percentage display for turbidity
                        document.getElementById('turbidityPercent').innerHTML = `<i class="fas fa-percentage mr-1"></i>${turbidityPercent.toFixed(1)}%`;
                        document.getElementById('tdsRaw').textContent = `Raw: ${parseFloat(latest.tds_ppm).toFixed(0)} ppm`;
                        document.getElementById('turbidityTime').textContent = `Last updated: ${formatDate(latest.reading_time)}`;
                        document.getElementById('tdsTime').textContent = `Last updated: ${formatDate(latest.reading_time)}`;
                        document.getElementById('phTime').textContent = `Last updated: ${formatDate(latest.reading_time)}`;
                        document.getElementById('temperatureTime').textContent = `Last updated: ${formatDate(latest.reading_time)}`;
                    }

                    // Update table
                    if (data.recent && data.recent.length > 0) {
                        const tableHtml = data.recent.map(reading => {
                            const turbidityPercent = convertTurbidityToPercentage(parseFloat(reading.turbidity_ntu));
                            const tdsPercent = convertTDSToPercentage(parseFloat(reading.tds_ppm));
                            
                            return `
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-300">${formatDate(reading.reading_time)}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-300">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">${parseFloat(reading.turbidity_ntu).toFixed(0)} NTU</div>
                                        <div class="text-xs text-blue-500 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 px-2 py-0.5 rounded-full inline-block mt-1">
                                            <i class="fas fa-percentage mr-1"></i>${turbidityPercent.toFixed(1)}%
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-300">
                                        <div class="font-medium">${tdsPercent.toFixed(1)}%</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Raw: ${parseFloat(reading.tds_ppm).toFixed(0)} ppm</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-300">${parseFloat(reading.ph).toFixed(2)}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-300">${parseFloat(reading.temperature).toFixed(2)}</td>
                                </tr>
                            `;
                        }).join('');
                        document.getElementById('readingsTable').innerHTML = tableHtml;
                    }

                    // Update chart
                    if (data.historical && data.historical.length > 0) {
                        updateChart(data.historical);
                    }

            // Update water quality alerts (pass raw values for proper threshold evaluation)
            updateWaterQualityAlerts(
                parseFloat(latest.turbidity_ntu),
                parseFloat(latest.tds_ppm),
                parseFloat(latest.ph),
                parseFloat(latest.temperature)
            );
            
            // Debug: Log current acknowledgment state
            console.log('Dashboard updateData - acknowledgedAlerts:', Array.from(acknowledgedAlerts));
            console.log('Dashboard updateData - localStorage:', Object.keys(readAckStorage()));
                })
                .catch(error => {
                    console.error('Error fetching data:', error);
                    document.getElementById('turbidityValue').textContent = 'Error';
                    document.getElementById('tdsValue').textContent = 'Error';
                    document.getElementById('turbidityTime').textContent = 'Failed to update';
                    document.getElementById('tdsTime').textContent = 'Failed to update';
                    document.getElementById('readingsTable').innerHTML = '<tr><td colspan="3" class="px-4 py-3 text-sm text-red-500 dark:text-red-400">Error loading data</td></tr>';
                });
        }

        function updateChart(data) {
            const ctx = document.getElementById('readingsChart').getContext('2d');
            
            if (readingsChart instanceof Chart) {
                readingsChart.destroy();
            }

            // Create gradient for each dataset
            const turbidityGradient = ctx.createLinearGradient(0, 0, 0, 400);
            turbidityGradient.addColorStop(0, 'rgba(59, 130, 246, 0.2)');
            turbidityGradient.addColorStop(1, 'rgba(59, 130, 246, 0)');

            const tdsGradient = ctx.createLinearGradient(0, 0, 0, 400);
            tdsGradient.addColorStop(0, 'rgba(16, 185, 129, 0.2)');
            tdsGradient.addColorStop(1, 'rgba(16, 185, 129, 0)');

            const phGradient = ctx.createLinearGradient(0, 0, 0, 400);
            phGradient.addColorStop(0, 'rgba(168, 85, 247, 0.2)');
            phGradient.addColorStop(1, 'rgba(168, 85, 247, 0)');

            const tempGradient = ctx.createLinearGradient(0, 0, 0, 400);
            tempGradient.addColorStop(0, 'rgba(239, 68, 68, 0.2)');
            tempGradient.addColorStop(1, 'rgba(239, 68, 68, 0)');

            readingsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => formatDate(d.reading_time)),
                    datasets: [{
                        label: 'Turbidity (NTU)',
                        data: data.map(d => parseFloat(d.turbidity_ntu)),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: turbidityGradient,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: 'rgb(59, 130, 246)',
                        pointBorderColor: document.documentElement.classList.contains('dark') ? '#1F2937' : '#fff',
                        pointBorderWidth: 2,
                        pointHoverBackgroundColor: document.documentElement.classList.contains('dark') ? '#1F2937' : '#fff',
                        pointHoverBorderColor: 'rgb(59, 130, 246)',
                        pointHoverBorderWidth: 2,
                        pointStyle: 'circle'
                    }, {
                        label: 'TDS (%)',
                        data: data.map(d => convertTDSToPercentage(parseFloat(d.tds_ppm))),
                        borderColor: 'rgb(16, 185, 129)',
                        backgroundColor: tdsGradient,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: 'rgb(16, 185, 129)',
                        pointBorderColor: document.documentElement.classList.contains('dark') ? '#1F2937' : '#fff',
                        pointBorderWidth: 2,
                        pointHoverBackgroundColor: document.documentElement.classList.contains('dark') ? '#1F2937' : '#fff',
                        pointHoverBorderColor: 'rgb(16, 185, 129)',
                        pointHoverBorderWidth: 2,
                        pointStyle: 'circle'
                    }, {
                        label: 'pH',
                        data: data.map(d => parseFloat(d.ph)),
                        borderColor: 'rgb(168, 85, 247)',
                        backgroundColor: phGradient,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: 'rgb(168, 85, 247)',
                        pointBorderColor: document.documentElement.classList.contains('dark') ? '#1F2937' : '#fff',
                        pointBorderWidth: 2,
                        pointHoverBackgroundColor: document.documentElement.classList.contains('dark') ? '#1F2937' : '#fff',
                        pointHoverBorderColor: 'rgb(168, 85, 247)',
                        pointHoverBorderWidth: 2,
                        pointStyle: 'circle'
                    }, {
                        label: 'Temperature (°C)',
                        data: data.map(d => parseFloat(d.temperature)),
                        borderColor: 'rgb(239, 68, 68)',
                        backgroundColor: tempGradient,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: 'rgb(239, 68, 68)',
                        pointBorderColor: document.documentElement.classList.contains('dark') ? '#1F2937' : '#fff',
                        pointBorderWidth: 2,
                        pointHoverBackgroundColor: document.documentElement.classList.contains('dark') ? '#1F2937' : '#fff',
                        pointHoverBorderColor: 'rgb(239, 68, 68)',
                        pointHoverBorderWidth: 2,
                        pointStyle: 'circle'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: getChartColors().grid,
                                drawBorder: false
                            },
                            ticks: {
                                padding: 10,
                                font: {
                                    size: 11
                                },
                                color: getChartColors().text
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                padding: 10,
                                font: {
                                    size: 11
                                },
                                color: getChartColors().text,
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                font: {
                                    size: 12,
                                    weight: '500'
                                },
                                color: document.documentElement.classList.contains('dark') ? '#F3F4F6' : '#374151'
                            }
                        },
                        tooltip: {
                            backgroundColor: getChartColors().tooltipBg,
                            titleColor: getChartColors().tooltipText,
                            bodyColor: getChartColors().tooltipText,
                            borderColor: getChartColors().tooltipBorder,
                            borderWidth: 1,
                            padding: 12,
                            boxPadding: 6,
                            usePointStyle: true,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += context.parsed.y.toFixed(2);
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    elements: {
                        line: {
                            tension: 0.4
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        }

        // Update data and relay states every second with 500ms initial delay
        setTimeout(async () => {
            // Clean expired local per-sensor acknowledgments first
            clearExpiredAcknowledgments();
            
            // Load server-side acknowledgments
            await loadAcknowledgedAlerts();
            
            // Also sync any local acknowledgments that might be valid
            const localAcks = readAckStorage();
            Object.keys(localAcks).forEach(sensorType => {
                if (!acknowledgedAlerts.has(sensorType)) {
                    acknowledgedAlerts.add(sensorType);
                    console.log(`Restored local acknowledgment for ${sensorType}`);
                }
            });
            
            console.log('Acknowledgments loaded:', {
                server: Array.from(acknowledgedAlerts),
                local: Object.keys(localAcks)
            });
            
            await loadAcknowledgmentStats();
            updateData();
            fetchRelayStates();
            refreshAcknowledgmentReports();
            
            // Force refresh water quality alerts to show acknowledgment status
            setTimeout(() => {
                const turbidity = parseFloat(document.getElementById('turbidityValue').textContent) || 0;
                const tds = parseFloat(document.getElementById('tdsValue').textContent) || 0;
                const ph = parseFloat(document.getElementById('phValue').textContent) || 0;
                const temperature = parseFloat(document.getElementById('temperatureValue').textContent) || 0;
                updateWaterQualityAlerts(turbidity, tds, ph, temperature);
            }, 1000);
        }, 500); // Initial 500ms delay
        
        setInterval(() => {
            updateData();
            fetchRelayStates();
        }, 1000); // Update every 1 second instead of 5 seconds
        
        setInterval(() => {
            clearExpiredAcknowledgments();
            loadAcknowledgedAlerts(); // Reload acknowledgments periodically
            loadAcknowledgmentStats();
            refreshAcknowledgmentReports();
        }, 30000); // Update acknowledgment data every 30 seconds

        // Add current time update
        function updateCurrentTime() {
            const now = new Date();
            document.getElementById('currentTime').textContent = now.toLocaleTimeString();
        }
        setInterval(updateCurrentTime, 1000);
        updateCurrentTime();

        // Update table row colors for dark mode
        function updateTableRowColors() {
            const rows = document.querySelectorAll('#readingsTable tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                cells.forEach(cell => {
                    cell.classList.add('dark:text-gray-300');
                });
            });
        }

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
            
            // Refresh the chart with new colors
            if (readingsChart) {
                readingsChart.destroy();
            }
            updateData(); // This will recreate the chart with new colors
            
            // Update table colors
            updateTableRowColors();
        });

        // Update chart colors based on theme
        function getChartColors() {
            const isDark = document.documentElement.classList.contains('dark');
            return {
                grid: isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)',
                text: isDark ? '#9CA3AF' : '#6B7280',
                tooltipBg: isDark ? 'rgba(31, 41, 55, 0.95)' : 'rgba(255, 255, 255, 0.95)',
                tooltipText: isDark ? '#F3F4F6' : '#1F2937',
                tooltipBorder: isDark ? '#374151' : '#E5E7EB'
            };
        }

        // Water quality thresholds
        const thresholds = {
            turbidity: {
                good: 2,      // NTU (0-2 NTU = good)
                warning: 5,   // NTU (2-5 NTU = medium)
                danger: 5     // NTU (>5 NTU = critical)
            },
            tds: {
                good: 30,     // % (0-30% = good)
                warning: 60,  // % (30-60% = medium)
                danger: 60    // % (>60% = critical)
            },
            ph: {
                good: { min: 6, max: 8 },        // Good range
                warning: { min: 4, max: 10 },    // Medium range (4-6 and 8-10)
                danger: { min: 0, max: 14 }      // Critical range (below 4 and above 10)
            },
            temperature: {
                good: { min: 15, max: 25 },    // °C (Optimal for most water systems)
                warning: { min: 10, max: 30 }, // °C (Acceptable range)
                danger: { min: 5, max: 35 }    // °C (Critical range)
            }
        };

        // Conversion functions
        function convertTurbidityToPercentage(rawValue) {
            // Formula: (Raw Value - 1) / 2999 * 100
            return Math.max(0, Math.min(100, ((rawValue - 1) / 2999) * 100));
        }

        function convertTDSToPercentage(ppmValue) {
            // Convert TDS ppm to percentage (assuming max reasonable TDS is ~1000 ppm)
            return Math.max(0, Math.min(100, (ppmValue / 1000) * 100));
        }

        function evaluateWaterQuality(turbidity, tds, ph, temperature) {
            const alerts = [];
            
            // Convert raw values to percentages for display
            const turbidityPercent = convertTurbidityToPercentage(turbidity);
            const tdsPercent = convertTDSToPercentage(tds);
            
            // Evaluate Turbidity (using raw NTU values for thresholds)
            if (turbidity >= thresholds.turbidity.danger) {
                alerts.push({
                    type: 'danger',
                    message: `High turbidity (${turbidity.toFixed(0)} NTU, ${turbidityPercent.toFixed(1)}%) - Water is very cloudy and may contain harmful particles`
                });
            } else if (turbidity >= thresholds.turbidity.warning) {
                alerts.push({
                    type: 'warning',
                    message: `Medium turbidity (${turbidity.toFixed(0)} NTU, ${turbidityPercent.toFixed(1)}%) - Water clarity is reduced`
                });
            } else if (turbidity <= thresholds.turbidity.good) {
                alerts.push({
                    type: 'success',
                    message: `Good turbidity (${turbidity.toFixed(0)} NTU, ${turbidityPercent.toFixed(1)}%) - Water is clear`
                });
            }

            // Evaluate TDS
            if (tdsPercent >= thresholds.tds.danger) {
                alerts.push({
                    type: 'danger',
                    message: `High TDS (${tdsPercent.toFixed(1)}%) - Water contains excessive dissolved solids`
                });
            } else if (tdsPercent >= thresholds.tds.warning) {
                alerts.push({
                    type: 'warning',
                    message: `Medium TDS (${tdsPercent.toFixed(1)}%) - Water may need treatment`
                });
            } else if (tdsPercent <= thresholds.tds.good) {
                alerts.push({
                    type: 'success',
                    message: `Good TDS (${tdsPercent.toFixed(1)}%) - Water is within acceptable range`
                });
            }

            // Evaluate pH (New standards)
            if (ph < 4 || ph > 10) {
                alerts.push({
                    type: 'danger',
                    message: `Critical pH (${ph.toFixed(1)}) - Water pH is extremely outside safe range`
                });
            } else if ((ph >= 4 && ph < 6) || (ph > 8 && ph <= 10)) {
                alerts.push({
                    type: 'warning',
                    message: `Medium pH (${ph.toFixed(1)}) - Water pH needs monitoring and adjustment`
                });
            } else if (ph >= 6 && ph <= 8) {
                alerts.push({
                    type: 'success',
                    message: `Good pH (${ph.toFixed(1)}) - Water pH is within ideal range`
                });
            }

            // Evaluate Temperature (Realistic water quality standards)
            if (temperature < thresholds.temperature.danger.min || temperature > thresholds.temperature.danger.max) {
                alerts.push({
                    type: 'danger',
                    message: `Critical temperature (${temperature.toFixed(1)}°C) - Water temperature is outside safe range`
                });
            } else if (temperature < thresholds.temperature.warning.min || temperature > thresholds.temperature.warning.max) {
                alerts.push({
                    type: 'warning',
                    message: `Unusual temperature (${temperature.toFixed(1)}°C) - Monitor water temperature closely`
                });
            } else if (temperature >= thresholds.temperature.good.min && temperature <= thresholds.temperature.good.max) {
                alerts.push({
                    type: 'success',
                    message: `Good temperature (${temperature.toFixed(1)}°C) - Water is at optimal temperature`
                });
            }

            return alerts;
        }

        // Track unacknowledged alerts
        let unacknowledgedAlerts = new Map();
        let currentAlertData = null;
        let acknowledgedAlerts = new Set(); // Track acknowledged alerts to prevent re-showing
        let lastAlertCheck = new Map(); // Track when we last checked for alerts

        // Pagination variables for acknowledgment reports
        let acknowledgmentReports = [];
        let currentAckPage = 1;
        const ackItemsPerPage = 5;

        // Per-sensor acknowledgment persistence (5 hours)
        const ACK_DURATION_MINUTES = 300; // 5 hours
        const ACK_STORAGE_KEY = 'sensorAcknowledgments';

        function readAckStorage() {
            try {
                const raw = localStorage.getItem(ACK_STORAGE_KEY);
                return raw ? JSON.parse(raw) : {};
            } catch (_) {
                return {};
            }
        }

        function writeAckStorage(map) {
            try {
                localStorage.setItem(ACK_STORAGE_KEY, JSON.stringify(map));
            } catch (_) { /* ignore */ }
        }

        function clearExpiredAcknowledgments() {
            const now = Date.now();
            const map = readAckStorage();
            let changed = false;
            Object.keys(map).forEach(sensor => {
                if (!map[sensor] || typeof map[sensor].expiresAt !== 'number' || map[sensor].expiresAt <= now) {
                    delete map[sensor];
                    changed = true;
                }
            });
            if (changed) writeAckStorage(map);
        }

        function isSensorAcknowledged(sensorType) {
            clearExpiredAcknowledgments();
            const map = readAckStorage();
            return Boolean(map[sensorType]);
        }

        function setSensorAcknowledged(sensorType) {
            const now = Date.now();
            const expiresAt = now + ACK_DURATION_MINUTES * 60 * 1000;
            const map = readAckStorage();
            map[sensorType] = { acknowledgedAt: now, expiresAt };
            writeAckStorage(map);
        }

        function updateWaterQualityAlerts(turbidity, tds, ph, temperature) {
            const alertsContainer = document.getElementById('waterQualityAlerts');
            const alerts = evaluateWaterQuality(turbidity, tds, ph, temperature);
            
            // Convert TDS to percentage for acknowledgment logic
            const tdsPercent = convertTDSToPercentage(tds);
            
            // Filter alerts that need acknowledgment (danger level turbidity, TDS, and pH; warning level TDS and pH)
            const acknowledgmentAlerts = alerts.filter(alert => 
                (alert.type === 'danger' && alert.message.includes('turbidity')) ||
                (alert.type === 'danger' && alert.message.includes('TDS')) ||
                (alert.type === 'danger' && alert.message.includes('pH')) ||
                (alert.type === 'warning' && alert.message.includes('TDS')) ||
                (alert.type === 'warning' && alert.message.includes('pH'))
            );
            
            // Update unacknowledged alerts - create unique keys for each alert instance
            acknowledgmentAlerts.forEach(alert => {
                const alertType = alert.message.includes('turbidity') ? 'turbidity' : 
                                 alert.message.includes('TDS') ? 'tds' : 'ph';
                const alertLevel = alert.type; // 'danger' or 'warning'
                const now = new Date();
                
                // Create a unique key that includes type and level for better tracking
                const alertKey = `${alertType}_${alertLevel}`;
                const lastCheck = lastAlertCheck.get(alertKey);
                
                // Only check database every 30 seconds to avoid too many requests
                if (!lastCheck || (now - lastCheck) > 30000) {
                    lastAlertCheck.set(alertKey, now);
                    
                    // Check if already acknowledged in database
                    checkAlertAcknowledged(alertType, alert.message, now.toISOString()).then(isAcknowledged => {
                        if (isAcknowledged) {
                            acknowledgedAlerts.add(alertKey);
                            unacknowledgedAlerts.delete(alertKey);
                            updateUnacknowledgedCount();
                            // Re-render alerts to update UI
                            updateWaterQualityAlerts(turbidity, tds, ph, temperature);
                        }
                    });
                }
                
                // Only add to unacknowledged if not already tracked
                if (!unacknowledgedAlerts.has(alertKey) && !acknowledgedAlerts.has(alertKey)) {
                    unacknowledgedAlerts.set(alertKey, {
                        alert: alert,
                        alertType: alertType,
                        alertLevel: alertLevel,
                        timestamp: new Date(),
                        values: { turbidity, tds, ph, temperature }
                    });
                }
                
                // If it's already acknowledged, make sure it stays acknowledged
                if (acknowledgedAlerts.has(alertType) || isSensorAcknowledged(alertType)) {
                    acknowledgedAlerts.add(alertKey);
                }
            });
            
            // Clean up unacknowledged alerts that are now acknowledged
            unacknowledgedAlerts.forEach((alertData, alertKey) => {
                const alertType = alertData.alertType;
                if (acknowledgedAlerts.has(alertType) || isSensorAcknowledged(alertType)) {
                    unacknowledgedAlerts.delete(alertKey);
                    console.log(`Removed acknowledged alert from unacknowledged: ${alertKey}`);
                }
            });
            
            // Update unacknowledged count after processing all alerts
            updateUnacknowledgedCount();
            
            alertsContainer.innerHTML = alerts.map(alert => {
                const alertType = alert.message.includes('turbidity') ? 'turbidity' : 
                                 alert.message.includes('TDS') ? 'tds' :
                                 alert.message.includes('pH') ? 'ph' : null;
                const alertLevel = alert.type;
                const alertKey = alertType ? `${alertType}_${alertLevel}` : null;
                
                // Only show acknowledge button for danger/warning alerts that need acknowledgment
                const needsAcknowledgment = alertType && (
                    (alert.type === 'danger' && (alertType === 'turbidity' || alertType === 'tds' || alertType === 'ph')) ||
                    (alert.type === 'warning' && (alertType === 'tds' || alertType === 'ph'))
                );
                
                // Check if this specific alert type is acknowledged (same logic as alerts page)
                const isAcknowledged = alertType && (acknowledgedAlerts.has(alertType) || isSensorAcknowledged(alertType));
                const isUnacknowledged = alertKey && unacknowledgedAlerts.has(alertKey) && !isAcknowledged;
                
                // Debug logging
                if (alertType && needsAcknowledgment) {
                    console.log(`Dashboard Alert: ${alertType} (${alertLevel}) - isAcknowledged: ${isAcknowledged}, isUnacknowledged: ${isUnacknowledged}, acknowledgedAlerts:`, Array.from(acknowledgedAlerts), 'localStorage:', isSensorAcknowledged(alertType));
                }
                
                return `
                    <div class="flex items-center justify-between p-4 rounded-lg ${
                    alert.type === 'danger' ? 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300' :
                    alert.type === 'warning' ? 'bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-300' :
                    'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300'
                }">
                        <div class="flex items-center">
                    <i class="fas ${
                        alert.type === 'danger' ? 'fa-exclamation-circle' :
                        alert.type === 'warning' ? 'fa-exclamation-triangle' :
                        'fa-check-circle'
                    } mr-3"></i>
                    <span>${alert.message}</span>
                </div>
                        ${needsAcknowledgment && isUnacknowledged ? `
                            <button onclick="openAcknowledgeModal('${alertKey}')" 
                                    class="ml-4 px-4 py-2 text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 dark:bg-amber-700 dark:hover:bg-amber-600 rounded-lg transition-colors shadow-sm">
                                <i class="fas fa-shield-alt mr-2"></i>Acknowledge
                            </button>
                        ` : needsAcknowledgment && isAcknowledged ? `
                            <span class="ml-4 px-4 py-2 text-sm font-medium text-emerald-700 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 rounded-lg">
                                <i class="fas fa-check-circle mr-2"></i>Acknowledged (5h)
                            </span>
                        ` : ''}
                    </div>
                `;
            }).join('');
        }

        function updateUnacknowledgedCount() {
            // Count only unacknowledged alerts that are not acknowledged
            let count = 0;
            unacknowledgedAlerts.forEach((alertData, alertKey) => {
                const alertType = alertData.alertType;
                // Only count if the alert type is not acknowledged (either in server or local storage)
                if (!acknowledgedAlerts.has(alertType) && !isSensorAcknowledged(alertType)) {
                    count++;
                }
            });
            
            const countElement = document.getElementById('unackCount');
            const containerElement = document.getElementById('unacknowledgedCount');
            
            countElement.textContent = count;
            if (count > 0) {
                containerElement.classList.remove('hidden');
            } else {
                containerElement.classList.add('hidden');
            }
            
            console.log(`Unacknowledged count updated: ${count} (total unacknowledged: ${unacknowledgedAlerts.size}, acknowledged: ${acknowledgedAlerts.size})`);
        }

        function openAcknowledgeModal(alertKey) {
            const alertData = unacknowledgedAlerts.get(alertKey);
            if (!alertData) return;
            
            currentAlertData = { key: alertKey, data: alertData };
            
            // Update modal content
            const modalDetails = document.getElementById('modalAlertDetails');
            modalDetails.innerHTML = `
                <div class="text-sm">
                    <div class="font-semibold text-amber-800 dark:text-amber-200 mb-3 flex items-center">
                        <i class="fas fa-info-circle mr-2"></i>Alert Details
                    </div>
                    <div class="mb-3 p-3 bg-amber-100 dark:bg-amber-900/30 rounded-lg border border-amber-200 dark:border-amber-700">
                        <div class="text-amber-900 dark:text-amber-100 font-medium leading-relaxed">
                            ${alertData.alert.message}
                        </div>
                    </div>
                    <div class="text-xs text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 px-2 py-1 rounded border border-amber-200 dark:border-amber-800">
                        <i class="fas fa-clock mr-1"></i>Detected at: ${alertData.timestamp.toLocaleString()}
                    </div>
                </div>
            `;
            
            // Reset form
            document.getElementById('acknowledgeForm').reset();
            
            // Show modal
            document.getElementById('acknowledgeModal').classList.remove('hidden');
        }

        function closeAcknowledgeModal() {
            document.getElementById('acknowledgeModal').classList.add('hidden');
            currentAlertData = null;
        }

        // Modal event listeners
        document.getElementById('closeModal').addEventListener('click', closeAcknowledgeModal);
        document.getElementById('cancelAcknowledge').addEventListener('click', closeAcknowledgeModal);
        
        // Close modal when clicking outside
        document.getElementById('acknowledgeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAcknowledgeModal();
            }
        });

        // Handle form submission
        document.getElementById('acknowledgeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!currentAlertData) return;
            
            const formData = new FormData(this);
            const acknowledgeData = {
                alert_type: currentAlertData.data.alertType, // Use the actual alert type (turbidity, tds, ph)
                alert_message: currentAlertData.data.alert.message,
                action_taken: formData.get('action_taken'),
                details: formData.get('details'),
                responsible_person: formData.get('responsible_person'),
                timestamp: currentAlertData.data.timestamp.toISOString(),
                values: currentAlertData.data.values
            };
            
            // Submit acknowledgment
            fetch('../../api/acknowledge_alert.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(acknowledgeData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove from unacknowledged alerts and add to acknowledged
                    unacknowledgedAlerts.delete(currentAlertData.key);
                    acknowledgedAlerts.add(currentAlertData.data.alertType); // Use alertType, not key
                    
                    // Persist per-sensor acknowledgment for 5 hours locally
                    setSensorAcknowledged(currentAlertData.data.alertType);
                    console.log(`Acknowledged ${currentAlertData.data.alertType} - now persisting locally and on server`);
                    
                    updateUnacknowledgedCount();
                    
                    // Close modal
                    closeAcknowledgeModal();
                    
                    // Show success message
                    showNotification('Alert acknowledged successfully', 'success');
                    
                    // Refresh alerts and reports to update UI
                    updateData();
                    refreshAcknowledgmentReports();
                } else {
                    showNotification('Failed to acknowledge alert: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error acknowledging alert', 'error');
            });
        });

        // Check if alert has been acknowledged in the database
        async function checkAlertAcknowledged(alertKey, alertMessage, timestamp) {
            try {
                const response = await fetch('../../api/check_alert_acknowledged.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        alert_type: alertKey,
                        alert_message: alertMessage,
                        alert_timestamp: timestamp
                    })
                });
                
                const data = await response.json();
                return data.success && data.acknowledged;
            } catch (error) {
                console.error('Error checking alert acknowledgment:', error);
                return false;
            }
        }

        // Load already acknowledged alerts on page load
        async function loadAcknowledgedAlerts() {
            try {
                const response = await fetch('../../api/get_acknowledgments.php?limit=100', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = await response.json();
                if (data.success && data.data) {
                    // Mark recent acknowledgments as acknowledged
                    const now = new Date();
                    data.data.forEach(report => {
                        const ackTime = new Date(report.acknowledged_at);
                        const timeDiff = now - ackTime;
                        const fiveHoursInMs = 5 * 60 * 60 * 1000; // 5 hours in milliseconds
                        
                        // Mark as acknowledged if acknowledged within last 5 hours
                        if (timeDiff < fiveHoursInMs) {
                            acknowledgedAlerts.add(report.alert_type);
                            console.log(`Loaded acknowledged alert: ${report.alert_type} from ${report.acknowledged_at} (${Math.round(timeDiff / (60 * 1000))} minutes ago)`);
                            // Mirror into local storage with expiry at ackTime + 5h
                            try {
                                const map = readAckStorage();
                                const expiresAt = ackTime.getTime() + fiveHoursInMs;
                                if (!map[report.alert_type] || (typeof map[report.alert_type].expiresAt === 'number' && map[report.alert_type].expiresAt < expiresAt)) {
                                    map[report.alert_type] = { acknowledgedAt: ackTime.getTime(), expiresAt };
                                    writeAckStorage(map);
                                }
                            } catch (_) { /* ignore */ }
                        }
                    });
                    
                    console.log(`Total acknowledged alerts loaded: ${acknowledgedAlerts.size}`);
                    console.log('Acknowledged alert types:', Array.from(acknowledgedAlerts));
                }
            } catch (error) {
                console.error('Error loading acknowledged alerts:', error);
            }
        }

        // Update stat with animation
        function updateStatWithAnimation(elementId, newValue) {
            const element = document.getElementById(elementId);
            if (element) {
                // Add pulse animation
                element.classList.add('animate-pulse');
                element.style.transform = 'scale(1.1)';
                
                // Update value
                element.textContent = newValue;
                
                // Remove animation after a short delay
                setTimeout(() => {
                    element.classList.remove('animate-pulse');
                    element.style.transform = 'scale(1)';
                }, 600);
            }
        }

        // Load acknowledgment statistics
        async function loadAcknowledgmentStats() {
            try {
                const response = await fetch('../../api/get_acknowledgment_stats.php', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                if (data.success && data.data) {
                    // Update statistics display with animation
                    updateStatWithAnimation('totalAcknowledged', data.data.total_acknowledged || 0);
                    updateStatWithAnimation('todayAcknowledged', data.data.today_acknowledged || 0);
                    
                    // Show warning if there's a database error
                    if (data.error) {
                        console.warn('Database connection issue:', data.error);
                    }
                } else {
                    console.error('Failed to load acknowledgment stats:', data);
                    updateStatWithAnimation('totalAcknowledged', 0);
                    updateStatWithAnimation('todayAcknowledged', 0);
                }
            } catch (error) {
                console.error('Error loading acknowledgment stats:', error);
                document.getElementById('totalAcknowledged').textContent = '--';
                document.getElementById('todayAcknowledged').textContent = '--';
            }
        }

        function refreshAcknowledgmentReports() {
            // Load stats first
            loadAcknowledgmentStats();
            
            // Then load reports
            fetch('../../api/get_acknowledgments.php', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.data) {
                    // Store all reports and reset to first page
                    acknowledgmentReports = data.data;
                    currentAckPage = 1;
                    
                    // Update total count
                    document.getElementById('totalAcknowledgmentCount').textContent = acknowledgmentReports.length;
                    
                    // Render paginated table
                    renderAcknowledgmentReports();
                } else {
                    throw new Error(data.error || 'Failed to load acknowledgment reports');
                }
            })
            .catch(error => {
                console.error('Error loading acknowledgment reports:', error);
                const tableBody = document.getElementById('acknowledgmentReportsTable');
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-red-500 dark:text-red-400">
                            <i class="fas fa-exclamation-circle mr-2"></i>Error loading acknowledgment reports
                        </td>
                    </tr>
                `;
                updateAcknowledgmentPaginationInfo();
            });
        }

        function renderAcknowledgmentReports() {
            const tableBody = document.getElementById('acknowledgmentReportsTable');
            
            if (acknowledgmentReports.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            <i class="fas fa-clipboard-list mr-2"></i>No acknowledgment reports found
                        </td>
                    </tr>
                `;
                updateAcknowledgmentPaginationInfo();
                return;
            }
            
            // Calculate pagination
            const startIndex = (currentAckPage - 1) * ackItemsPerPage;
            const endIndex = startIndex + ackItemsPerPage;
            const paginatedReports = acknowledgmentReports.slice(startIndex, endIndex);
            
            tableBody.innerHTML = paginatedReports.map(report => {
                const actionLabels = {
                    'filter_replacement': 'Filter Replacement',
                    'system_maintenance': 'System Maintenance',
                    'chemical_treatment': 'Chemical Treatment',
                    'system_flush': 'System Flush',
                    'investigation': 'Under Investigation',
                    'manual_intervention': 'Manual Intervention',
                    'other': 'Other'
                };
                
                const alertTypeLabels = {
                    'turbidity': 'Turbidity',
                    'tds': 'TDS',
                    'ph': 'pH'
                };
                
                const actionLabel = actionLabels[report.action_taken] || report.action_taken;
                const alertTypeLabel = alertTypeLabels[report.alert_type] || report.alert_type;
                
                return `
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-300">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                report.alert_type === 'turbidity' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300' :
                                report.alert_type === 'tds' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300' :
                                'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300'
                            }">
                                <i class="fas ${
                                    report.alert_type === 'turbidity' ? 'fa-filter' : 
                                    report.alert_type === 'tds' ? 'fa-flask' : 'fa-vial'
                                } mr-1"></i>
                                ${alertTypeLabel}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-300">
                            <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300">
                                <i class="fas fa-tools mr-1"></i>
                                ${actionLabel}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-300">
                            <div class="flex items-center">
                                <i class="fas fa-user-circle mr-2 text-gray-400"></i>
                                ${report.responsible_person || 'Unknown'}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-300">
                            <div class="flex items-center">
                                <i class="fas fa-clock mr-2 text-gray-400"></i>
                                ${formatDate(report.acknowledged_at)}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-300 max-w-xs">
                            <div class="truncate" title="${report.details}">
                                ${report.details}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
            
            updateAcknowledgmentPaginationInfo();
            updateAcknowledgmentPaginationButtons();
        }

        function updateAcknowledgmentPaginationInfo() {
            const totalPages = Math.ceil(acknowledgmentReports.length / ackItemsPerPage);
            const startIndex = (currentAckPage - 1) * ackItemsPerPage + 1;
            const endIndex = Math.min(currentAckPage * ackItemsPerPage, acknowledgmentReports.length);
            
            const paginationInfo = document.getElementById('ackPaginationInfo');
            if (acknowledgmentReports.length === 0) {
                paginationInfo.textContent = 'Showing 0 to 0 of 0 results';
            } else {
                paginationInfo.textContent = `Showing ${startIndex} to ${endIndex} of ${acknowledgmentReports.length} results`;
            }
        }

        function updateAcknowledgmentPaginationButtons() {
            const totalPages = Math.ceil(acknowledgmentReports.length / ackItemsPerPage);
            const prevButton = document.getElementById('ackPrevPage');
            const nextButton = document.getElementById('ackNextPage');
            const pageNumbersContainer = document.getElementById('ackPageNumbers');
            
            // Update prev/next buttons
            prevButton.disabled = currentAckPage <= 1;
            nextButton.disabled = currentAckPage >= totalPages;
            
            // Generate page numbers
            pageNumbersContainer.innerHTML = '';
            
            if (totalPages <= 7) {
                // Show all pages if 7 or fewer
                for (let i = 1; i <= totalPages; i++) {
                    pageNumbersContainer.appendChild(createAcknowledgmentPageNumberButton(i));
                }
            } else {
                // Show first page, last page, current page, and pages around current
                if (currentAckPage > 3) {
                    pageNumbersContainer.appendChild(createAcknowledgmentPageNumberButton(1));
                    if (currentAckPage > 4) {
                        pageNumbersContainer.appendChild(createAcknowledgmentPageNumberButton('...'));
                    }
                }
                
                const start = Math.max(1, currentAckPage - 2);
                const end = Math.min(totalPages, currentAckPage + 2);
                
                for (let i = start; i <= end; i++) {
                    pageNumbersContainer.appendChild(createAcknowledgmentPageNumberButton(i));
                }
                
                if (currentAckPage < totalPages - 2) {
                    if (currentAckPage < totalPages - 3) {
                        pageNumbersContainer.appendChild(createAcknowledgmentPageNumberButton('...'));
                    }
                    pageNumbersContainer.appendChild(createAcknowledgmentPageNumberButton(totalPages));
                }
            }
        }

        function createAcknowledgmentPageNumberButton(page) {
            const button = document.createElement('button');
            button.className = `px-3 py-1 text-sm rounded-lg transition-colors ${
                page === currentAckPage 
                    ? 'bg-blue-500 text-white' 
                    : page === '...'
                        ? 'text-gray-500 dark:text-gray-400 cursor-default'
                        : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
            }`;
            button.textContent = page;
            button.disabled = page === '...';
            
            if (page !== '...') {
                button.addEventListener('click', () => goToAcknowledgmentPage(page));
            }
            
            return button;
        }

        function goToAcknowledgmentPage(page) {
            currentAckPage = page;
            renderAcknowledgmentReports();
        }

        // Export acknowledgment reports to CSV
        function exportAcknowledgmentReports() {
            // Get acknowledgment reports from the current data
            fetch('../../api/get_acknowledgments.php?limit=1000')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data && data.data.length > 0) {
                        const reports = data.data;
                        
                        // Prepare CSV content
                        const headers = ['Time', 'Alert Type', 'Action Taken', 'Responsible Person', 'Details', 'Acknowledged At'];
                        const csvContent = [
                            headers.join(','),
                            ...reports.map(report => [
                                `"${formatDate(report.acknowledged_at)}"`,
                                `"${report.alert_type.toUpperCase()}"`,
                                `"${report.action_taken}"`,
                                `"${report.responsible_person}"`,
                                `"${(report.details || 'No additional details').replace(/"/g, '""')}"`,
                                `"${new Date(report.acknowledged_at).toLocaleDateString()}"`
                            ].join(','))
                        ].join('\n');

                        // Create and download file
                        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                        const link = document.createElement('a');
                        
                        if (link.download !== undefined) {
                            const url = URL.createObjectURL(blob);
                            link.setAttribute('href', url);
                            link.setAttribute('download', `acknowledgment_reports_${new Date().toISOString().split('T')[0]}.csv`);
                            link.style.visibility = 'hidden';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            URL.revokeObjectURL(url);
                            
                            showNotification(`Exported ${reports.length} acknowledgment reports`, 'success');
                        } else {
                            showNotification('Export not supported in this browser', 'error');
                        }
                    } else {
                        showNotification('No acknowledgment reports to export', 'info');
                    }
                })
                .catch(error => {
                    console.error('Error exporting acknowledgment reports:', error);
                    showNotification('Error exporting acknowledgment reports', 'error');
                });
        }

        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed bottom-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 ease-in-out ${
                type === 'success' ? 'bg-emerald-500 dark:bg-emerald-600 text-white' :
                type === 'error' ? 'bg-red-500 dark:bg-red-600 text-white' :
                'bg-blue-500 dark:bg-blue-600 text-white'
            }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${
                        type === 'success' ? 'fa-check-circle' :
                        type === 'error' ? 'fa-exclamation-circle' :
                        'fa-info-circle'
                    } mr-2"></i>
                    <span class="font-medium">${message}</span>
                </div>
                <button onclick="this.closest('.fixed').remove()" class="absolute top-1 right-1 text-white hover:text-gray-200">
                    <i class="fas fa-times text-sm"></i>
                </button>
            `;
            
            // Add slide-in animation
            notification.style.transform = 'translateX(100%)';
            document.body.appendChild(notification);
            
            // Trigger slide-in animation
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 10);
            
            // Remove after 5 seconds
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 5000);
        }
        
        // Add event listeners for export button and pagination
        document.addEventListener('DOMContentLoaded', function() {
            const exportBtn = document.getElementById('exportAcknowledgmentReports');
            if (exportBtn) {
                exportBtn.addEventListener('click', exportAcknowledgmentReports);
            }
            
            // Pagination event listeners
            document.getElementById('ackPrevPage').addEventListener('click', () => {
                if (currentAckPage > 1) {
                    currentAckPage--;
                    renderAcknowledgmentReports();
                }
            });
            
            document.getElementById('ackNextPage').addEventListener('click', () => {
                const totalPages = Math.ceil(acknowledgmentReports.length / ackItemsPerPage);
                if (currentAckPage < totalPages) {
                    currentAckPage++;
                    renderAcknowledgmentReports();
                }
            });
        });
    </script>
        </div>
    </div>
</body>
</html> 