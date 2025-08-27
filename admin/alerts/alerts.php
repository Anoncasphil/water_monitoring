<?php
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login/index.php');
    exit;
}

require_once '../../config/database.php';

// Get consistent readings (only legitimate, non-manipulated readings)
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get legitimate readings for alerts (where manipulation is not detected)
    $result = $conn->query("
        SELECT * FROM water_readings 
        WHERE (turbidity BETWEEN 0.5 AND 100) 
          AND (tds BETWEEN 0 AND 2000) 
          AND (ph BETWEEN 4.0 AND 10.0) 
          AND (temperature BETWEEN 10 AND 50)
        ORDER BY reading_time DESC 
        LIMIT 10
    ");
    $readings = $result->fetch_all(MYSQLI_ASSOC);
    
    // Get manipulation status for the last hour
    $manipulationStatus = $conn->query("
        SELECT 
            COUNT(*) as total_readings,
            SUM(CASE WHEN (turbidity > 100 OR tds > 2000 OR ph < 4.0 OR ph > 10.0 OR temperature < 10 OR temperature > 50) THEN 1 ELSE 0 END) as manipulated_count,
            SUM(CASE WHEN (turbidity BETWEEN 0.5 AND 100) AND (tds BETWEEN 0 AND 2000) AND (ph BETWEEN 4.0 AND 10.0) AND (temperature BETWEEN 10 AND 50) THEN 1 ELSE 0 END) as legitimate_count
        FROM water_readings 
        WHERE reading_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ")->fetch_assoc();
    
} catch (Exception $e) {
    $readings = [];
    $manipulationStatus = ['total_readings' => 0, 'manipulated_count' => 0, 'legitimate_count' => 0];
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Quality Alerts - Water Quality System</title>
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
        .alert-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .alert-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #EF4444, #F59E0B, #10B981, #3B82F6);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        .alert-card:hover::before {
            transform: scaleX(1);
        }
        .alert-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .dark .alert-card:hover {
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
        .status-critical {
            background-color: #FEE2E2;
            color: #DC2626;
        }
        .dark .status-critical {
            background-color: #DC2626;
            color: #FEE2E2;
        }
        .status-warning {
            background-color: #FEF3C7;
            color: #D97706;
        }
        .dark .status-warning {
            background-color: #D97706;
            color: #FEF3C7;
        }
        .status-good {
            background-color: #DEF7EC;
            color: #03543F;
        }
        .dark .status-good {
            background-color: #03543F;
            color: #DEF7EC;
        }
        .status-info {
            background-color: #E0E7FF;
            color: #3730A3;
        }
        .dark .status-info {
            background-color: #3730A3;
            color: #E0E7FF;
        }
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body class="gradient-bg min-h-screen transition-colors duration-300">
    <!-- Include Sidebar -->
    <?php include '../sidebar/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64">
        <div class="container mx-auto px-6 py-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-10">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                        Water Quality Alerts
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 text-lg">Monitor and manage water quality alerts and notifications</p>
                </div>
                <div class="flex items-center space-x-6">
                    <div class="text-center">
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">System Status</div>
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 bg-green-500 rounded-full pulse-animation"></div>
                            <span class="text-lg font-semibold text-green-600 dark:text-green-400">Monitoring</span>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-center">
                            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Data Integrity</div>
                            <div class="flex items-center space-x-2">
                                <div class="w-3 h-3 <?php echo $manipulationStatus['manipulated_count'] > 0 ? 'bg-yellow-500' : 'bg-green-500'; ?> rounded-full pulse-animation"></div>
                                <span class="text-sm font-medium <?php echo $manipulationStatus['manipulated_count'] > 0 ? 'text-yellow-600 dark:text-yellow-400' : 'text-green-600 dark:text-green-400'; ?>">
                                    <?php echo $manipulationStatus['manipulated_count'] > 0 ? 'Mixed Data' : 'Clean Data'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="text-center">
                            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Legitimate/Total</div>
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                <?php echo $manipulationStatus['legitimate_count']; ?>/<?php echo $manipulationStatus['total_readings']; ?>
                            </div>
                        </div>
                    </div>
                    <button id="themeToggle" class="p-3 rounded-xl bg-white dark:bg-gray-800 shadow-lg hover:shadow-xl transition-all duration-200">
                        <i class="fas fa-sun text-yellow-500 dark:hidden text-lg"></i>
                        <i class="fas fa-moon text-blue-300 hidden dark:block text-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Data Integrity Overview -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                            <i class="fas fa-shield-alt text-blue-500 mr-3"></i>
                            Data Integrity Overview
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400">Data quality and manipulation detection status</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-center">
                            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Data Status</div>
                            <div class="text-lg font-semibold <?php echo $manipulationStatus['manipulated_count'] > 0 ? 'text-yellow-600 dark:text-yellow-400' : 'text-green-600 dark:text-green-400'; ?>">
                                <?php echo $manipulationStatus['manipulated_count'] > 0 ? 'Mixed Data Detected' : 'Clean Data Only'; ?>
                            </div>
                        </div>
                        <button id="refreshReadings" class="px-4 py-2 bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300 rounded-lg hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors">
                            <i class="fas fa-sync-alt mr-2"></i>Refresh
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="alert-card bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-xl p-6 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-green-800 dark:text-green-200">Legitimate Readings</h3>
                                <p class="text-3xl font-bold text-green-600 dark:text-green-400"><?php echo $manipulationStatus['legitimate_count']; ?></p>
                                <p class="text-sm text-green-600 dark:text-green-300">Clean, non-manipulated data</p>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-green-200 dark:bg-green-800 flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-600 dark:text-green-300 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="alert-card bg-gradient-to-br from-yellow-50 to-yellow-100 dark:from-yellow-900/20 dark:to-yellow-800/20 rounded-xl p-6 border-l-4 border-yellow-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-yellow-800 dark:text-yellow-200">Manipulated Readings</h3>
                                <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400"><?php echo $manipulationStatus['manipulated_count']; ?></p>
                                <p class="text-sm text-yellow-600 dark:text-yellow-300">Extreme values detected</p>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-yellow-200 dark:bg-yellow-800 flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-yellow-600 dark:text-yellow-300 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="alert-card bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-xl p-6 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200">Total Readings</h3>
                                <p class="text-3xl font-bold text-blue-600 dark:text-blue-400"><?php echo $manipulationStatus['total_readings']; ?></p>
                                <p class="text-sm text-blue-600 dark:text-blue-300">Last hour total</p>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-blue-200 dark:bg-blue-800 flex items-center justify-center">
                                <i class="fas fa-database text-blue-600 dark:text-blue-300 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-4 rounded">
                    <p class="text-sm text-blue-800 dark:text-blue-200">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Data Filtering:</strong> This system now automatically filters out readings with extreme values that indicate manipulation:
                        <strong>Turbidity > 100 NTU</strong>, <strong>TDS > 2000 ppm</strong>, <strong>pH < 4.0 or > 10.0</strong>, <strong>Temperature < 10°C or > 50°C</strong>.
                        Only legitimate, consistent readings are displayed to ensure data reliability.
                    </p>
                </div>
            </div>

            <!-- System Status Overview -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                            <i class="fas fa-chart-line text-blue-500 mr-3"></i>
                            System Status Overview
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400">Current water quality status and alert summary</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-center">
                            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Last Update</div>
                            <div class="text-lg font-semibold text-gray-900 dark:text-white" id="lastUpdate">--:--:--</div>
                        </div>
                        <button id="refreshReadings" class="px-4 py-2 bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300 rounded-lg hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors">
                            <i class="fas fa-sync-alt mr-2"></i>Refresh
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="alert-card bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/20 dark:to-red-800/20 rounded-xl p-6 border-l-4 border-red-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-red-800 dark:text-red-200">Critical Alerts</h3>
                                <p class="text-3xl font-bold text-red-600 dark:text-red-400" id="criticalAlerts">0</p>
                                <p class="text-sm text-red-600 dark:text-red-300">Requires immediate attention</p>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-red-200 dark:bg-red-800 flex items-center justify-center">
                                <i class="fas fa-exclamation-circle text-red-600 dark:text-red-300 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="alert-card bg-gradient-to-br from-yellow-50 to-yellow-100 dark:from-yellow-900/20 dark:to-yellow-800/20 rounded-xl p-6 border-l-4 border-yellow-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-yellow-800 dark:text-yellow-200">Warnings</h3>
                                <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400" id="warningAlerts">0</p>
                                <p class="text-sm text-yellow-600 dark:text-yellow-300">Monitor closely</p>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-yellow-200 dark:bg-yellow-800 flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-yellow-600 dark:text-yellow-300 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="alert-card bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-xl p-6 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-green-800 dark:text-green-200">Good Status</h3>
                                <p class="text-3xl font-bold text-green-600 dark:text-green-400" id="goodAlerts">0</p>
                                <p class="text-sm text-green-600 dark:text-green-300">All parameters normal</p>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-green-200 dark:bg-green-800 flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-600 dark:text-green-300 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="alert-card bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-xl p-6 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200">Total Alerts</h3>
                                <p class="text-3xl font-bold text-blue-600 dark:text-blue-400" id="totalAlerts">0</p>
                                <p class="text-sm text-blue-600 dark:text-blue-300">Last 24 hours</p>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-blue-200 dark:bg-blue-800 flex items-center justify-center">
                                <i class="fas fa-bell text-blue-600 dark:text-blue-300 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>



            <!-- Quick Status & Active Alerts -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                <!-- Quick Status -->
                <div class="lg:col-span-1">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                <i class="fas fa-tachometer-alt text-blue-500 mr-2"></i>
                                Quick Status
                            </h3>
                            <div class="w-3 h-3 bg-green-500 rounded-full pulse-animation"></div>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-filter text-blue-500 mr-3"></i>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Turbidity</span>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white" id="quickTurbidity">--</div>
                                    <div class="text-xs text-gray-500" id="quickTurbidityStatus">--</div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-flask text-emerald-500 mr-3"></i>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">TDS</span>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white" id="quickTDS">--</div>
                                    <div class="text-xs text-gray-500" id="quickTDSStatus">--</div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-vial text-purple-500 mr-3"></i>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">pH</span>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white" id="quickPH">--</div>
                                    <div class="text-xs text-gray-500" id="quickPHStatus">--</div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-thermometer-half text-red-500 mr-3"></i>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Temperature</span>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white" id="quickTemp">--</div>
                                    <div class="text-xs text-gray-500" id="quickTempStatus">--</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="text-center">
                                <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Overall Status</div>
                                <div class="text-lg font-bold text-green-600 dark:text-green-400" id="overallStatus">Good</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Alerts -->
                <div class="lg:col-span-2">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6 h-full">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <i class="fas fa-bell text-red-500 mr-2"></i>
                                    Active Alerts
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Current water quality issues requiring attention</p>
                            </div>
                            <div class="flex space-x-2">
                                <button id="acknowledgeAll" class="px-3 py-1 text-sm bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors">
                                    <i class="fas fa-check mr-1"></i>Acknowledge All
                                </button>
                                <button id="clearAllAlerts" class="px-3 py-1 text-sm bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors">
                                    <i class="fas fa-trash mr-1"></i>Clear All
                                </button>
                            </div>
                        </div>

                        <div id="activeAlertsContainer" class="space-y-3 max-h-96 overflow-y-auto">
                            <!-- Alerts will be dynamically inserted here -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert History -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                            <i class="fas fa-history text-blue-500 mr-3"></i>
                            Alert History
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400">Historical water quality alerts and events</p>
                    </div>
                                     <div class="flex space-x-3">
                     <select id="filterType" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white">
                         <option value="all">All Types</option>
                         <option value="critical">Critical</option>
                         <option value="warning">Warning</option>
                         <option value="good">Good</option>
                     </select>
                     <button id="exportHistory" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors">
                         <i class="fas fa-download mr-2"></i>Export
                     </button>
                 </div>


             <!-- Pagination -->
             <div class="flex items-center justify-between mt-6">
                 <div class="flex items-center space-x-2 text-sm text-gray-700 dark:text-gray-300">
                     <span>Showing</span>
                     <span id="showingStart">1</span>
                     <span>to</span>
                     <span id="showingEnd">10</span>
                     <span>of</span>
                     <span id="totalItems">0</span>
                     <span>alerts</span>
                 </div>
                 <div class="flex items-center space-x-2">
                     <button id="prevPage" class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                         <i class="fas fa-chevron-left mr-1"></i>Previous
                     </button>
                     <div class="flex items-center space-x-1" id="pageNumbers">
                         <!-- Page numbers will be dynamically inserted here -->
                     </div>
                     <button id="nextPage" class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                         Next<i class="fas fa-chevron-right ml-1"></i>
                     </button>
                 </div>
             </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Time</th>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Type</th>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Parameter</th>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Value</th>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Message</th>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Status</th>
                            </tr>
                        </thead>
                        <tbody id="alertHistoryTable" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <!-- Alert history will be dynamically inserted here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Water quality thresholds (same as dashboard)
        const thresholds = {
            turbidity: {
                good: 5,      // NTU
                warning: 10,  // NTU
                danger: 20    // NTU
            },
            tds: {
                good: 300,    // ppm
                warning: 500, // ppm
                danger: 1000  // ppm
            },
            ph: {
                good: { min: 6.5, max: 8.5 },
                warning: { min: 6.0, max: 9.0 },
                danger: { min: 5.0, max: 10.0 }
            },
            temperature: {
                good: { min: 15, max: 30 },    // °C
                warning: { min: 10, max: 35 }, // °C
                danger: { min: 5, max: 40 }    // °C
            }
        };

        let alertHistory = [];
        let currentPage = 1;
        const itemsPerPage = 10;

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleTimeString();
        }

        function evaluateWaterQuality(turbidity, tds, ph, temperature) {
            const alerts = [];
            
            // Evaluate Turbidity
            if (turbidity >= thresholds.turbidity.danger) {
                alerts.push({
                    type: 'critical',
                    parameter: 'Turbidity',
                    value: turbidity.toFixed(1) + ' NTU',
                    message: `High turbidity (${turbidity.toFixed(1)} NTU) - Water is very cloudy and may contain harmful particles`,
                    time: new Date().toISOString()
                });
            } else if (turbidity >= thresholds.turbidity.warning) {
                alerts.push({
                    type: 'warning',
                    parameter: 'Turbidity',
                    value: turbidity.toFixed(1) + ' NTU',
                    message: `Elevated turbidity (${turbidity.toFixed(1)} NTU) - Water clarity is reduced`,
                    time: new Date().toISOString()
                });
            } else if (turbidity <= thresholds.turbidity.good) {
                alerts.push({
                    type: 'good',
                    parameter: 'Turbidity',
                    value: turbidity.toFixed(1) + ' NTU',
                    message: `Good turbidity (${turbidity.toFixed(1)} NTU) - Water is clear`,
                    time: new Date().toISOString()
                });
            }

            // Evaluate TDS
            if (tds >= thresholds.tds.danger) {
                alerts.push({
                    type: 'critical',
                    parameter: 'TDS',
                    value: tds.toFixed(0) + ' ppm',
                    message: `High TDS (${tds.toFixed(0)} ppm) - Water contains excessive dissolved solids`,
                    time: new Date().toISOString()
                });
            } else if (tds >= thresholds.tds.warning) {
                alerts.push({
                    type: 'warning',
                    parameter: 'TDS',
                    value: tds.toFixed(0) + ' ppm',
                    message: `Elevated TDS (${tds.toFixed(0)} ppm) - Water may need treatment`,
                    time: new Date().toISOString()
                });
            } else if (tds <= thresholds.tds.good) {
                alerts.push({
                    type: 'good',
                    parameter: 'TDS',
                    value: tds.toFixed(0) + ' ppm',
                    message: `Good TDS (${tds.toFixed(0)} ppm) - Water is within acceptable range`,
                    time: new Date().toISOString()
                });
            }

            // Evaluate pH
            if (ph < thresholds.ph.danger.min || ph > thresholds.ph.danger.max) {
                alerts.push({
                    type: 'critical',
                    parameter: 'pH',
                    value: ph.toFixed(1),
                    message: `Extreme pH (${ph.toFixed(1)}) - Water is too acidic or alkaline`,
                    time: new Date().toISOString()
                });
            } else if (ph < thresholds.ph.warning.min || ph > thresholds.ph.warning.max) {
                alerts.push({
                    type: 'warning',
                    parameter: 'pH',
                    value: ph.toFixed(1),
                    message: `Unbalanced pH (${ph.toFixed(1)}) - Water may need pH adjustment`,
                    time: new Date().toISOString()
                });
            } else if (ph >= thresholds.ph.good.min && ph <= thresholds.ph.good.max) {
                alerts.push({
                    type: 'good',
                    parameter: 'pH',
                    value: ph.toFixed(1),
                    message: `Good pH (${ph.toFixed(1)}) - Water is within ideal range`,
                    time: new Date().toISOString()
                });
            }

            // Evaluate Temperature
            if (temperature < thresholds.temperature.danger.min || temperature > thresholds.temperature.danger.max) {
                alerts.push({
                    type: 'critical',
                    parameter: 'Temperature',
                    value: temperature.toFixed(1) + '°C',
                    message: `Extreme temperature (${temperature.toFixed(1)}°C) - Water is too hot or cold`,
                    time: new Date().toISOString()
                });
            } else if (temperature < thresholds.temperature.warning.min || temperature > thresholds.temperature.warning.max) {
                alerts.push({
                    type: 'warning',
                    parameter: 'Temperature',
                    value: temperature.toFixed(1) + '°C',
                    message: `Unusual temperature (${temperature.toFixed(1)}°C) - Monitor water temperature`,
                    time: new Date().toISOString()
                });
            } else if (temperature >= thresholds.temperature.good.min && temperature <= thresholds.temperature.good.max) {
                alerts.push({
                    type: 'good',
                    parameter: 'Temperature',
                    value: temperature.toFixed(1) + '°C',
                    message: `Good temperature (${temperature.toFixed(1)}°C) - Water is at ideal temperature`,
                    time: new Date().toISOString()
                });
            }

            return alerts;
        }

        function updateSensorReadings(data) {
            if (data.latest) {
                const latest = data.latest;
                
                // Update quick status values
                document.getElementById('quickTurbidity').textContent = parseFloat(latest.turbidity_ntu).toFixed(1) + ' NTU';
                document.getElementById('quickTDS').textContent = parseFloat(latest.tds_ppm).toFixed(0) + ' ppm';
                document.getElementById('quickPH').textContent = parseFloat(latest.ph).toFixed(1);
                document.getElementById('quickTemp').textContent = parseFloat(latest.temperature).toFixed(1) + '°C';
                
                // Update quick status indicators
                updateQuickStatus('quickTurbidityStatus', parseFloat(latest.turbidity_ntu), 'turbidity');
                updateQuickStatus('quickTDSStatus', parseFloat(latest.tds_ppm), 'tds');
                updateQuickStatus('quickPHStatus', parseFloat(latest.ph), 'ph');
                updateQuickStatus('quickTempStatus', parseFloat(latest.temperature), 'temperature');
                
                // Update last update time
                document.getElementById('lastUpdate').textContent = formatDate(latest.reading_time);
                
                // Generate alerts
                const alerts = evaluateWaterQuality(
                    parseFloat(latest.turbidity_ntu),
                    parseFloat(latest.tds_ppm),
                    parseFloat(latest.ph),
                    parseFloat(latest.temperature)
                );
                
                updateActiveAlerts(alerts);
                updateAlertStatistics(alerts);
                updateAlertHistory(alerts);
                updateOverallStatus(alerts);
            }
        }

        function updateQuickStatus(elementId, value, parameter) {
            const element = document.getElementById(elementId);
            let status = 'Good';
            let color = 'text-green-600';
            
            switch(parameter) {
                case 'turbidity':
                    if (value >= thresholds.turbidity.danger) {
                        status = 'Critical';
                        color = 'text-red-600';
                    } else if (value >= thresholds.turbidity.warning) {
                        status = 'Warning';
                        color = 'text-yellow-600';
                    }
                    break;
                case 'tds':
                    if (value >= thresholds.tds.danger) {
                        status = 'Critical';
                        color = 'text-red-600';
                    } else if (value >= thresholds.tds.warning) {
                        status = 'Warning';
                        color = 'text-yellow-600';
                    }
                    break;
                case 'ph':
                    if (value < thresholds.ph.danger.min || value > thresholds.ph.danger.max) {
                        status = 'Critical';
                        color = 'text-red-600';
                    } else if (value < thresholds.ph.warning.min || value > thresholds.ph.warning.max) {
                        status = 'Warning';
                        color = 'text-yellow-600';
                    }
                    break;
                case 'temperature':
                    if (value < thresholds.temperature.danger.min || value > thresholds.temperature.danger.max) {
                        status = 'Critical';
                        color = 'text-red-600';
                    } else if (value < thresholds.temperature.warning.min || value > thresholds.temperature.warning.max) {
                        status = 'Warning';
                        color = 'text-yellow-600';
                    }
                    break;
            }
            
            element.textContent = status;
            element.className = `text-xs ${color}`;
        }

        function updateOverallStatus(alerts) {
            const criticalCount = alerts.filter(a => a.type === 'critical').length;
            const warningCount = alerts.filter(a => a.type === 'warning').length;
            
            const overallStatusElement = document.getElementById('overallStatus');
            
            if (criticalCount > 0) {
                overallStatusElement.textContent = 'Critical';
                overallStatusElement.className = 'text-lg font-bold text-red-600 dark:text-red-400';
            } else if (warningCount > 0) {
                overallStatusElement.textContent = 'Warning';
                overallStatusElement.className = 'text-lg font-bold text-yellow-600 dark:text-yellow-400';
            } else {
                overallStatusElement.textContent = 'Good';
                overallStatusElement.className = 'text-lg font-bold text-green-600 dark:text-green-400';
            }
        }

        function updateStatusBadge(elementId, value, parameter) {
            const element = document.getElementById(elementId);
            let status = 'good';
            let text = 'Good';
            
            switch(parameter) {
                case 'turbidity':
                    if (value >= thresholds.turbidity.danger) {
                        status = 'critical';
                        text = 'Critical';
                    } else if (value >= thresholds.turbidity.warning) {
                        status = 'warning';
                        text = 'Warning';
                    }
                    break;
                case 'tds':
                    if (value >= thresholds.tds.danger) {
                        status = 'critical';
                        text = 'Critical';
                    } else if (value >= thresholds.tds.warning) {
                        status = 'warning';
                        text = 'Warning';
                    }
                    break;
                case 'ph':
                    if (value < thresholds.ph.danger.min || value > thresholds.ph.danger.max) {
                        status = 'critical';
                        text = 'Critical';
                    } else if (value < thresholds.ph.warning.min || value > thresholds.ph.warning.max) {
                        status = 'warning';
                        text = 'Warning';
                    }
                    break;
                case 'temperature':
                    if (value < thresholds.temperature.danger.min || value > thresholds.temperature.danger.max) {
                        status = 'critical';
                        text = 'Critical';
                    } else if (value < thresholds.temperature.warning.min || value > thresholds.temperature.warning.max) {
                        status = 'warning';
                        text = 'Warning';
                    }
                    break;
            }
            
            element.className = `status-badge status-${status}`;
            element.textContent = text;
        }

        function updateActiveAlerts(alerts) {
            const container = document.getElementById('activeAlertsContainer');
            const criticalAlerts = alerts.filter(a => a.type === 'critical');
            const warningAlerts = alerts.filter(a => a.type === 'warning');
            
            // Show critical alerts first, then warnings
            const activeAlerts = [...criticalAlerts, ...warningAlerts];
            
            if (activeAlerts.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        <i class="fas fa-check-circle text-4xl mb-4 text-green-500"></i>
                        <p class="text-lg font-medium">No active alerts</p>
                        <p class="text-sm">All water quality parameters are within normal ranges</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = activeAlerts.map(alert => `
                <div class="alert-card p-6 rounded-xl border-l-4 ${
                    alert.type === 'critical' ? 'border-red-500 bg-red-50 dark:bg-red-900/20' :
                    'border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20'
                }">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <i class="fas ${
                                    alert.type === 'critical' ? 'fa-exclamation-circle text-red-500' :
                                    'fa-exclamation-triangle text-yellow-500'
                                } text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center space-x-2 mb-2">
                                    <span class="status-badge ${
                                        alert.type === 'critical' ? 'status-critical' : 'status-warning'
                                    }">${alert.type.toUpperCase()}</span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">${alert.parameter}</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">${alert.value}</span>
                                </div>
                                <p class="text-gray-900 dark:text-white">${alert.message}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                    <i class="fas fa-clock mr-1"></i>${formatDate(alert.time)}
                                </p>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="acknowledgeAlert('${alert.time}')" class="p-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">
                                <i class="fas fa-check"></i>
                            </button>
                            <button onclick="dismissAlert('${alert.time}')" class="p-2 text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300 transition-colors">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function updateAlertStatistics(alerts) {
            const critical = alerts.filter(a => a.type === 'critical').length;
            const warning = alerts.filter(a => a.type === 'warning').length;
            const good = alerts.filter(a => a.type === 'good').length;
            const total = critical + warning + good;
            
            document.getElementById('criticalAlerts').textContent = critical;
            document.getElementById('warningAlerts').textContent = warning;
            document.getElementById('goodAlerts').textContent = good;
            document.getElementById('totalAlerts').textContent = total;
        }

        function updateAlertHistory(alerts) {
            // Add new alerts to history
            alertHistory = [...alerts, ...alertHistory].slice(0, 100); // Keep last 100 alerts
            
            // Reset to first page when filter changes
            currentPage = 1;
            
            renderAlertHistory();
        }

        function renderAlertHistory() {
            const tableBody = document.getElementById('alertHistoryTable');
            const filterType = document.getElementById('filterType').value;
            
            let filteredAlerts = alertHistory;
            if (filterType !== 'all') {
                filteredAlerts = alertHistory.filter(alert => alert.type === filterType);
            }
            
            const totalItems = filteredAlerts.length;
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            
            // Ensure current page is valid
            if (currentPage > totalPages) {
                currentPage = totalPages || 1;
            }
            
            // Calculate pagination
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = Math.min(startIndex + itemsPerPage, totalItems);
            const currentAlerts = filteredAlerts.slice(startIndex, endIndex);
            
            // Update pagination info
            document.getElementById('showingStart').textContent = totalItems > 0 ? startIndex + 1 : 0;
            document.getElementById('showingEnd').textContent = endIndex;
            document.getElementById('totalItems').textContent = totalItems;
            
            // Update pagination buttons
            updatePaginationButtons(totalPages);
            
            if (currentAlerts.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                            <i class="fas fa-inbox text-2xl mb-2"></i>
                            <p>No alert history found</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tableBody.innerHTML = currentAlerts.map(alert => `
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">${formatDate(alert.time)}</td>
                    <td class="px-6 py-4">
                        <span class="status-badge ${
                            alert.type === 'critical' ? 'status-critical' :
                            alert.type === 'warning' ? 'status-warning' :
                            'status-good'
                        }">${alert.type.toUpperCase()}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">${alert.parameter}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">${alert.value}</td>
                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">${alert.message}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                            Active
                        </span>
                    </td>
                </tr>
            `).join('');
        }

        function updatePaginationButtons(totalPages) {
            const prevBtn = document.getElementById('prevPage');
            const nextBtn = document.getElementById('nextPage');
            const pageNumbersContainer = document.getElementById('pageNumbers');
            
            // Update Previous/Next buttons
            prevBtn.disabled = currentPage <= 1;
            nextBtn.disabled = currentPage >= totalPages;
            
            // Generate page numbers
            let pageNumbersHTML = '';
            const maxVisiblePages = 5;
            
            if (totalPages <= maxVisiblePages) {
                // Show all pages if total is small
                for (let i = 1; i <= totalPages; i++) {
                    pageNumbersHTML += createPageNumberButton(i, i === currentPage);
                }
            } else {
                // Show smart pagination with ellipsis
                if (currentPage <= 3) {
                    // Show first 3 pages + ellipsis + last page
                    for (let i = 1; i <= 3; i++) {
                        pageNumbersHTML += createPageNumberButton(i, i === currentPage);
                    }
                    pageNumbersHTML += '<span class="px-2 py-1 text-gray-500">...</span>';
                    pageNumbersHTML += createPageNumberButton(totalPages, false);
                } else if (currentPage >= totalPages - 2) {
                    // Show first page + ellipsis + last 3 pages
                    pageNumbersHTML += createPageNumberButton(1, false);
                    pageNumbersHTML += '<span class="px-2 py-1 text-gray-500">...</span>';
                    for (let i = totalPages - 2; i <= totalPages; i++) {
                        pageNumbersHTML += createPageNumberButton(i, i === currentPage);
                    }
                } else {
                    // Show first page + ellipsis + current page + ellipsis + last page
                    pageNumbersHTML += createPageNumberButton(1, false);
                    pageNumbersHTML += '<span class="px-2 py-1 text-gray-500">...</span>';
                    for (let i = currentPage - 1; i <= currentPage + 1; i++) {
                        pageNumbersHTML += createPageNumberButton(i, i === currentPage);
                    }
                    pageNumbersHTML += '<span class="px-2 py-1 text-gray-500">...</span>';
                    pageNumbersHTML += createPageNumberButton(totalPages, false);
                }
            }
            
            pageNumbersContainer.innerHTML = pageNumbersHTML;
        }

        function createPageNumberButton(pageNum, isActive) {
            return `
                <button onclick="goToPage(${pageNum})" 
                        class="px-3 py-2 text-sm rounded-lg transition-colors ${
                            isActive 
                                ? 'bg-blue-500 text-white' 
                                : 'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300'
                        }">
                    ${pageNum}
                </button>
            `;
        }

        function goToPage(page) {
            currentPage = page;
            renderAlertHistory();
        }

        function acknowledgeAlert(time) {
            // Remove alert from active alerts
            alertHistory = alertHistory.filter(alert => alert.time !== time);
            updateActiveAlerts(alertHistory.filter(a => a.type === 'critical' || a.type === 'warning'));
            renderAlertHistory();
        }

        function dismissAlert(time) {
            // Remove alert completely
            alertHistory = alertHistory.filter(alert => alert.time !== time);
            updateActiveAlerts(alertHistory.filter(a => a.type === 'critical' || a.type === 'warning'));
            renderAlertHistory();
        }

        function fetchData() {
            fetch('../../api/get_readings.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    updateSensorReadings(data);
                })
                .catch(error => {
                    console.error('Error fetching data:', error);
                    document.getElementById('turbidityValue').textContent = 'Error';
                    document.getElementById('tdsValue').textContent = 'Error';
                    document.getElementById('phValue').textContent = 'Error';
                    document.getElementById('temperatureValue').textContent = 'Error';
                });
        }

        // Event listeners
        document.getElementById('refreshReadings').addEventListener('click', fetchData);
        document.getElementById('clearAllAlerts').addEventListener('click', () => {
            if (confirm('Are you sure you want to clear all alerts?')) {
                alertHistory = [];
                currentPage = 1;
                updateActiveAlerts([]);
                renderAlertHistory();
                updateAlertStatistics([]);
            }
        });
        document.getElementById('acknowledgeAll').addEventListener('click', () => {
            alertHistory = alertHistory.filter(alert => alert.type === 'good');
            currentPage = 1;
            updateActiveAlerts([]);
            renderAlertHistory();
            updateAlertStatistics(alertHistory);
        });
        document.getElementById('filterType').addEventListener('change', () => {
            currentPage = 1; // Reset to first page when filter changes
            renderAlertHistory();
        });

        // Pagination event listeners
        document.getElementById('prevPage').addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                renderAlertHistory();
            }
        });

        document.getElementById('nextPage').addEventListener('click', () => {
            const filterType = document.getElementById('filterType').value;
            let filteredAlerts = alertHistory;
            if (filterType !== 'all') {
                filteredAlerts = alertHistory.filter(alert => alert.type === filterType);
            }
            const totalPages = Math.ceil(filteredAlerts.length / itemsPerPage);
            
            if (currentPage < totalPages) {
                currentPage++;
                renderAlertHistory();
            }
        });
        document.getElementById('exportHistory').addEventListener('click', () => {
            const csvContent = "data:text/csv;charset=utf-8," 
                + "Time,Type,Parameter,Value,Message\n"
                + alertHistory.map(alert => 
                    `${formatDate(alert.time)},${alert.type},${alert.parameter},${alert.value},"${alert.message}"`
                ).join("\n");
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "alert_history.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
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

        // Initialize and update data every 5 seconds
        fetchData();
        setInterval(fetchData, 5000);
    </script>
</body>
</html>

