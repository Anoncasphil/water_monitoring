<?php
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login/index.php');
    exit;
}

require_once '../../config/database.php';

// Get 4th-to-last readings (skip the latest 3)
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $result = $conn->query("SELECT * FROM water_readings ORDER BY reading_time DESC LIMIT 10 OFFSET 3");
    $readings = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $readings = [];
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
        
        /* Responsive improvements */
        @media (max-width: 768px) {
            .lg\:ml-64 {
                margin-left: 0;
            }
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            .grid-cols-1.md\:grid-cols-2.lg\:grid-cols-3 {
                grid-template-columns: repeat(1, minmax(0, 1fr));
                gap: 1rem;
            }
            .text-3xl {
                font-size: 1.875rem;
            }
            .text-2xl {
                font-size: 1.5rem;
            }
            .p-6 {
                padding: 1rem;
            }
            .overflow-x-auto {
                -webkit-overflow-scrolling: touch;
            }
            .alert-card {
                margin-bottom: 1rem;
            }
        }
        
        @media (max-width: 640px) {
            .text-3xl {
                font-size: 1.5rem;
            }
            .text-2xl {
                font-size: 1.25rem;
            }
            .p-6 {
                padding: 0.75rem;
            }
            .grid.grid-cols-1.lg\:grid-cols-2 {
                grid-template-columns: 1fr;
            }
        }
        
        /* Performance optimizations */
        .alert-card {
            will-change: transform;
        }
        
        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
        
        /* Better touch targets for mobile */
        @media (max-width: 768px) {
            button, input, select, textarea {
                min-height: 44px;
            }
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
                    <button id="themeToggle" class="p-3 rounded-xl bg-white dark:bg-gray-800 shadow-lg hover:shadow-xl transition-all duration-200">
                        <i class="fas fa-sun text-yellow-500 dark:hidden text-lg"></i>
                        <i class="fas fa-moon text-blue-300 hidden dark:block text-lg"></i>
                    </button>
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

            <!-- Acknowledgment Reports -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                            <i class="fas fa-clipboard-check text-amber-500 mr-3"></i>
                            Acknowledgment Reports
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400">Recent alert acknowledgments and actions taken</p>
                        <div class="mt-2">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Total Acknowledgments: </span>
                            <span id="totalAcknowledgmentCount" class="text-lg font-semibold text-amber-600 dark:text-amber-400">0</span>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button id="exportAcknowledgmentReports" class="px-4 py-2 bg-green-100 dark:bg-green-900 text-green-600 dark:text-green-300 rounded-lg hover:bg-green-200 dark:hover:bg-green-800 transition-colors">
                            <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <button id="refreshAcknowledgmentReports" class="px-4 py-2 bg-amber-100 dark:bg-amber-900 text-amber-600 dark:text-amber-300 rounded-lg hover:bg-amber-200 dark:hover:bg-amber-800 transition-colors">
                            <i class="fas fa-sync-alt mr-2"></i>Refresh
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Total Acknowledged -->
                    <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-xl p-6 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-green-800 dark:text-green-200">Total Acknowledged</h3>
                                <p class="text-3xl font-bold text-green-600 dark:text-green-400" id="totalAcknowledged">0</p>
                                <p class="text-sm text-green-600 dark:text-green-300">All time</p>
                                <p class="text-xs text-green-500 dark:text-green-500 mt-1" id="dbStatusIndicator" style="display: none;">
                                    Database not connected
                                </p>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-green-200 dark:bg-green-800 flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-600 dark:text-green-300 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Today's Acknowledged -->
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-xl p-6 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200">Today's Acknowledged</h3>
                                <p class="text-3xl font-bold text-blue-600 dark:text-blue-400" id="todayAcknowledged">0</p>
                                <p class="text-sm text-blue-600 dark:text-blue-300">Last 24 hours</p>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-blue-200 dark:bg-blue-800 flex items-center justify-center">
                                <i class="fas fa-calendar-day text-blue-600 dark:text-blue-300 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination Info -->
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-2 text-sm text-gray-700 dark:text-gray-300">
                        <span>Showing</span>
                        <span id="ackShowingStart">1</span>
                        <span>to</span>
                        <span id="ackShowingEnd">10</span>
                        <span>of</span>
                        <span id="ackTotalItems">0</span>
                        <span>acknowledgments</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button id="ackPrevPage" class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-chevron-left mr-1"></i>Previous
                        </button>
                        <div class="flex items-center space-x-1" id="ackPageNumbers">
                            <!-- Page numbers will be dynamically inserted here -->
                        </div>
                        <button id="ackNextPage" class="px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            Next<i class="fas fa-chevron-right ml-1"></i>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Time</th>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Alert Type</th>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Action Taken</th>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Responsible Person</th>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white">Details</th>
                            </tr>
                        </thead>
                        <tbody id="acknowledgmentReportsTable" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <!-- Acknowledgment reports will be dynamically inserted here -->
                        </tbody>
                    </table>
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
        let ackEvtSrc = null;
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

        // Local overrides to ignore server-side acknowledgments temporarily
        const ACK_RESET_KEY = 'sensorAckResetOverrides';
        function readAckReset() {
            try {
                const raw = localStorage.getItem(ACK_RESET_KEY);
                return raw ? JSON.parse(raw) : {};
            } catch (_) {
                return {};
            }
        }
        function writeAckReset(map) {
            try {
                localStorage.setItem(ACK_RESET_KEY, JSON.stringify(map));
            } catch (_) { /* ignore */ }
        }
        function isResetActive(sensorType) {
            const map = readAckReset();
            const now = Date.now();
            if (map[sensorType] && typeof map[sensorType].expiresAt === 'number') {
                if (map[sensorType].expiresAt > now) return true;
                // cleanup expired
                delete map[sensorType];
                writeAckReset(map);
            }
            return false;
        }
        function setReset(sensorType, minutes = ACK_DURATION_MINUTES) {
            const now = Date.now();
            const expiresAt = now + Math.max(1, minutes) * 60 * 1000;
            const map = readAckReset();
            map[sensorType] = { setAt: now, expiresAt };
            writeAckReset(map);
        }
        function clearAcknowledgment(sensorType) {
            try {
                // Remove local acknowledgment persistence
                const ackMap = readAckStorage();
                if (ackMap[sensorType]) {
                    delete ackMap[sensorType];
                    writeAckStorage(ackMap);
                }
                // Remove from in-memory acknowledged set (both type and composite keys)
                acknowledgedAlerts.delete(sensorType);
                ['critical','warning','danger'].forEach(level => {
                    acknowledgedAlerts.delete(`${sensorType}_${level}`);
                });
                // Set override to ignore server-side acks for the same window (5h)
                setReset(sensorType);
                // Refresh UI
                updateActiveAlerts(alertHistory.filter(a => a.type === 'critical' || a.type === 'warning'));
                showNotification(`Acknowledgment reset for ${sensorType.toUpperCase()}`, 'info');
            } catch (e) {
                console.error('Failed to clear acknowledgment:', e);
            }
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

        function mergeTrackerAcksIntoLocal(acks) {
            if (!Array.isArray(acks)) return;
            const now = Date.now();
            const map = readAckStorage();
            let changed = false;
            acks.forEach(ack => {
                if (!ack || !ack.sensor_type) return;
                const remainingMinutes = typeof ack.minutes_remaining === 'number' ? ack.minutes_remaining : 0;
                const expiresAt = now + Math.max(0, remainingMinutes) * 60 * 1000;
                if (!map[ack.sensor_type] || (typeof map[ack.sensor_type].expiresAt === 'number' && map[ack.sensor_type].expiresAt < expiresAt)) {
                    map[ack.sensor_type] = { acknowledgedAt: now, expiresAt };
                    changed = true;
                }
            });
            if (changed) writeAckStorage(map);
        }

        let alertHistory = [];
        let currentPage = 1;
        const itemsPerPage = 10;
        
        // Acknowledgment pagination variables
        let acknowledgmentReports = [];
        let currentAckPage = 1;
        const ackItemsPerPage = 10;

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
            }

            return alerts;
        }

        function updateSensorReadings(data) {
            if (data.latest) {
                const latest = data.latest;
                const turbidity = parseFloat(latest.turbidity_ntu);
                const tds = parseFloat(latest.tds_ppm);
                const ph = parseFloat(latest.ph);
                const temperature = parseFloat(latest.temperature);
                
                // Update sensor states first
                updateSensorStates(turbidity, tds, ph);
                
                // Update quick status values
                document.getElementById('quickTurbidity').textContent = turbidity.toFixed(1) + ' NTU';
                document.getElementById('quickTDS').textContent = tds.toFixed(0) + ' ppm';
                document.getElementById('quickPH').textContent = ph.toFixed(1);
                document.getElementById('quickTemp').textContent = temperature.toFixed(1) + '°C';
                
                // Update quick status indicators
                updateQuickStatus('quickTurbidityStatus', turbidity, 'turbidity');
                updateQuickStatus('quickTDSStatus', tds, 'tds');
                updateQuickStatus('quickPHStatus', ph, 'ph');
                updateQuickStatus('quickTempStatus', temperature, 'temperature');
                
                // Update last update time
                document.getElementById('lastUpdate').textContent = formatDate(latest.reading_time);
                
                // Generate alerts
                const alerts = evaluateWaterQuality(turbidity, tds, ph, temperature);
                
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

        // Acknowledgment system variables
        let acknowledgedAlerts = new Set();
        let unacknowledgedAlerts = new Map();
        let lastAlertCheck = new Map();

        // Load acknowledged alerts from database
        async function loadAcknowledgedAlerts() {
            try {
                const response = await fetch('../../api/get_acknowledgments.php?limit=100', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    cache: 'no-store'
                });
                
                const data = await response.json();
                if (data.success && data.data) {
                    const now = new Date();
                    data.data.forEach(report => {
                        const ackTime = new Date(report.acknowledged_at);
                        const timeDiff = now - ackTime;
                        const fiveHoursInMs = 5 * 60 * 60 * 1000; // 5 hours in milliseconds
                        
                        if (timeDiff < fiveHoursInMs) {
                            // Respect local reset override
                            if (isResetActive(report.alert_type)) {
                                return;
                            }
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

        // Check if alert is acknowledged
        async function checkAlertAcknowledged(alertType, alertMessage, alertTimestamp) {
            try {
                const response = await fetch('../../api/check_alert_acknowledged.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        alert_type: alertType,
                        alert_message: alertMessage,
                        alert_timestamp: alertTimestamp
                    })
                });
                
                const data = await response.json();
                return data.success && data.acknowledged;
            } catch (error) {
                console.error('Error checking alert acknowledgment:', error);
                return false;
            }
        }

        // Submit acknowledgment
        async function submitAcknowledgment(alertType, alertMessage, alertTimestamp, actionTaken, details, responsiblePerson, values) {
            try {
                const response = await fetch('../../api/acknowledge_alert.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        alert_type: alertType,
                        alert_message: alertMessage,
                        action_taken: actionTaken,
                        details: details,
                        responsible_person: responsiblePerson,
                        timestamp: alertTimestamp,
                        values: values
                    })
                });
                
                const data = await response.json();
                return data.success;
            } catch (error) {
                console.error('Error submitting acknowledgment:', error);
                return false;
            }
        }

        // Show acknowledgment modal
        function showAcknowledgmentModal(alert) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md mx-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-check-circle text-amber-500 mr-2"></i>
                            Acknowledge Alert
                        </h3>
                        <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="mb-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <h4 class="font-medium text-gray-900 dark:text-white mb-2">Alert Details:</h4>
                        <p class="text-sm text-gray-700 dark:text-gray-300 mb-1">${alert.message}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            <i class="fas fa-clock mr-1"></i>Detected at: ${new Date(alert.time).toLocaleString()}
                        </p>
                    </div>
                    
                    <form id="acknowledgeForm">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Action Taken <span class="text-red-500">*</span>
                            </label>
                            <select name="action_taken" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                                <option value="">Select action...</option>
                                <option value="investigated">Investigated Issue</option>
                                <option value="corrected">Corrected Problem</option>
                                <option value="monitoring">Monitoring Closely</option>
                                <option value="maintenance">Scheduled Maintenance</option>
                                <option value="reported">Reported to Supervisor</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Additional Details
                            </label>
                            <textarea name="details" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500" placeholder="Describe what was done to address this alert..."></textarea>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Responsible Person <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="responsible_person" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500" placeholder="Enter your name">
                        </div>
                        
                        <div class="flex space-x-3">
                            <button type="submit" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                                <i class="fas fa-check mr-2"></i>Acknowledge
                            </button>
                            <button type="button" onclick="this.closest('.fixed').remove()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Handle form submission
            modal.querySelector('#acknowledgeForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const formData = new FormData(e.target);
                const alertType = alert.parameter.toLowerCase().replace(' ', '');
                const success = await submitAcknowledgment(
                    alertType,
                    alert.message,
                    alert.time,
                    formData.get('action_taken'),
                    formData.get('details'),
                    formData.get('responsible_person'),
                    { value: alert.value }
                );
                
                if (success) {
                    acknowledgedAlerts.add(alertType);
                    // Persist per-sensor acknowledgment for 5 hours locally
                    setSensorAcknowledged(alertType);
                    console.log(`Acknowledged ${alertType} - now persisting locally and on server`);
                    modal.remove();
                    updateActiveAlerts(alertHistory.filter(a => a.type === 'critical' || a.type === 'warning'));
                    showNotification('Alert acknowledged successfully!', 'success');
                    // SSE consumers get push via server marker update
                } else {
                    showNotification('Failed to acknowledge alert. Please try again.', 'error');
                }
            });
        }

        // Show bulk acknowledgment modal
        function showBulkAcknowledgmentModal(alerts) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            
            const alertSummary = alerts.map(alert => 
                `${alert.parameter}: ${alert.value} (${alert.type.toUpperCase()})`
            ).join('<br>');
            
            modal.innerHTML = `
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md mx-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-check-circle text-amber-500 mr-2"></i>
                            Acknowledge All Alerts
                        </h3>
                        <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="mb-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <h4 class="font-medium text-gray-900 dark:text-white mb-2">Alerts to Acknowledge:</h4>
                        <div class="text-sm text-gray-700 dark:text-gray-300">${alertSummary}</div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>This will acknowledge ${alerts.length} alert(s)
                        </p>
                    </div>
                    
                    <form id="bulkAcknowledgeForm">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Action Taken <span class="text-red-500">*</span>
                            </label>
                            <select name="action_taken" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                                <option value="">Select action...</option>
                                <option value="investigated">Investigated Issue</option>
                                <option value="corrected">Corrected Problem</option>
                                <option value="monitoring">Monitoring Closely</option>
                                <option value="maintenance">Scheduled Maintenance</option>
                                <option value="reported">Reported to Supervisor</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Additional Details
                            </label>
                            <textarea name="details" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500" placeholder="Describe what was done to address these alerts..."></textarea>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Responsible Person <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="responsible_person" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500" placeholder="Enter your name">
                        </div>
                        
                        <div class="flex space-x-3">
                            <button type="submit" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                                <i class="fas fa-check mr-2"></i>Acknowledge All
                            </button>
                            <button type="button" onclick="this.closest('.fixed').remove()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Handle form submission
            modal.querySelector('#bulkAcknowledgeForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const formData = new FormData(e.target);
                const actionTaken = formData.get('action_taken');
                const details = formData.get('details');
                const responsiblePerson = formData.get('responsible_person');
                
                let successCount = 0;
                let failCount = 0;
                
                // Acknowledge each alert
                for (const alert of alerts) {
                    const alertType = alert.parameter.toLowerCase().replace(' ', '');
                    const success = await submitAcknowledgment(
                        alertType,
                        alert.message,
                        alert.time,
                        actionTaken,
                        details,
                        responsiblePerson,
                        { value: alert.value }
                    );
                    
                    if (success) {
                        acknowledgedAlerts.add(alertType);
                        // Persist per-sensor acknowledgment for 5 hours locally
                        setSensorAcknowledged(alertType);
                        console.log(`Bulk acknowledged ${alertType} - now persisting locally and on server`);
                        successCount++;
                    } else {
                        failCount++;
                    }
                }
                
                modal.remove();
                
                if (successCount > 0) {
                    showNotification(`Successfully acknowledged ${successCount} alert(s)`, 'success');
                    updateActiveAlerts(alertHistory.filter(a => a.type === 'critical' || a.type === 'warning'));
                    loadAcknowledgmentStats();
                    refreshAcknowledgmentReports();
                    // SSE consumers get push via server marker update
                }
                
                if (failCount > 0) {
                    showNotification(`Failed to acknowledge ${failCount} alert(s)`, 'error');
                }
            });
        }

        // Acknowledge alert in bulk (for acknowledge all functionality)
        async function acknowledgeAlertInBulk(alert) {
            const alertType = alert.parameter.toLowerCase().replace(' ', '');
            const actionTaken = 'investigated'; // Default action for bulk acknowledgment
            const details = 'Acknowledged via bulk acknowledgment';
            const responsiblePerson = 'System User'; // You can modify this to get from session
            
            const success = await submitAcknowledgment(
                alertType,
                alert.message,
                alert.time,
                actionTaken,
                details,
                responsiblePerson,
                { value: alert.value }
            );
            
            if (success) {
                acknowledgedAlerts.add(alertType);
                // Persist per-sensor acknowledgment for 5 hours locally
                setSensorAcknowledged(alertType);
            }
            
            return success;
        }

        // Show "Clear All Alerts" confirmation modal
        function showClearAllModal() {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md mx-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-trash text-red-500 mr-2"></i>
                            Clear All Alerts
                        </h3>
                        <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="mb-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            This will permanently clear all active alerts from the view. This action cannot be undone.
                        </p>
                    </div>

                    <div class="flex space-x-3">
                        <button id="confirmClearAll" class="flex-1 bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                            <i class="fas fa-trash mr-2"></i>Clear All
                        </button>
                        <button type="button" onclick="this.closest('.fixed').remove()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            Cancel
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            modal.querySelector('#confirmClearAll').addEventListener('click', () => {
                // Clear all alerts
                alertHistory = [];
                currentPage = 1;
                updateActiveAlerts([]);
                renderAlertHistory();
                updateAlertStatistics([]);

                // Close modal
                modal.remove();

                // Show bottom-right toast
                showNotification('All alerts have been cleared', 'success');
            });
        }

        // Show notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `fixed bottom-4 right-4 p-4 rounded-lg shadow-lg z-50 transform transition-all duration-300 ease-in-out ${
                type === 'success' ? 'bg-green-500 text-white' : 
                type === 'error' ? 'bg-red-500 text-white' :
                type === 'info' ? 'bg-blue-500 text-white' : 'bg-yellow-500 text-white'
            }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${
                        type === 'success' ? 'fa-check-circle' : 
                        type === 'error' ? 'fa-exclamation-circle' :
                        type === 'info' ? 'fa-info-circle' : 'fa-exclamation-triangle'
                    } mr-2"></i>
                    ${message}
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
            
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        // Track sensor states for better acknowledgment logic
        let sensorStates = {
            turbidity: { current: null, lastAcknowledged: null, lastStable: null },
            tds: { current: null, lastAcknowledged: null, lastStable: null },
            ph: { current: null, lastAcknowledged: null, lastStable: null }
        };

        function updateSensorStates(turbidity, tds, ph) {
            const now = new Date();
            
            // Update current sensor states
            sensorStates.turbidity.current = turbidity;
            sensorStates.tds.current = tds;
            sensorStates.ph.current = ph;
            
            // Check if sensors are stable (within good ranges)
            const turbidityStable = turbidity < thresholds.turbidity.warning;
            const tdsStable = tds < thresholds.tds.warning;
            const phStable = ph >= thresholds.ph.warning.min && ph <= thresholds.ph.warning.max;
            
            // Update last stable timestamps
            if (turbidityStable && !sensorStates.turbidity.lastStable) {
                sensorStates.turbidity.lastStable = now;
            } else if (!turbidityStable) {
                sensorStates.turbidity.lastStable = null;
            }
            
            if (tdsStable && !sensorStates.tds.lastStable) {
                sensorStates.tds.lastStable = now;
            } else if (!tdsStable) {
                sensorStates.tds.lastStable = null;
            }
            
            if (phStable && !sensorStates.ph.lastStable) {
                sensorStates.ph.lastStable = now;
            } else if (!phStable) {
                sensorStates.ph.lastStable = null;
            }
            
            // Note: We don't reset acknowledgment status here anymore
            // Acknowledgments persist for 5 hours regardless of sensor stability
        }

        function shouldShowAcknowledgeButton(alertType, alertLevel) {
            // Check if there's a recent acknowledgment for this sensor type
            const isServerAcknowledged = acknowledgedAlerts.has(alertType);
            const isLocalAcknowledged = isSensorAcknowledged(alertType);
            
            if (isServerAcknowledged || isLocalAcknowledged) {
                console.log(`Sensor ${alertType} is acknowledged (server: ${isServerAcknowledged}, local: ${isLocalAcknowledged})`);
                return false; // Don't show acknowledge button if recently acknowledged
            }
            
            return true;
        }

        function updateActiveAlerts(alerts) {
            const container = document.getElementById('activeAlertsContainer');
            const criticalAlerts = alerts.filter(a => a.type === 'critical');
            const warningAlerts = alerts.filter(a => a.type === 'warning');
            
            console.log('Updating active alerts:', {
                total: alerts.length,
                critical: criticalAlerts.length,
                warning: warningAlerts.length,
                acknowledgedTypes: Array.from(acknowledgedAlerts),
                localStorage: Object.keys(readAckStorage())
            });
            
            // Filter alerts that need acknowledgment (only for the 3 specific sensors)
            const acknowledgmentAlerts = alerts.filter(alert => 
                (alert.type === 'critical' && alert.message.includes('turbidity')) ||
                (alert.type === 'critical' && alert.message.includes('TDS')) ||
                (alert.type === 'critical' && alert.message.includes('pH')) ||
                (alert.type === 'warning' && alert.message.includes('TDS')) ||
                (alert.type === 'warning' && alert.message.includes('pH'))
            );
            
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
            
            // Play enhanced sound for warning/critical with debounce - only for unacknowledged alerts
            try {
                // Filter alerts that are not acknowledged
                const unacknowledgedCritical = criticalAlerts.filter(alert => {
                    const alertType = alert.message.includes('turbidity') ? 'turbidity' : 
                                     alert.message.includes('TDS') ? 'tds' :
                                     alert.message.includes('pH') ? 'ph' : null;
                    
                    if (!alertType) return false;
                    
                    // Check if this alert type is acknowledged
                    const isAcknowledged = acknowledgedAlerts.has(alertType) || isSensorAcknowledged(alertType);
                    return !isAcknowledged;
                });
                
                const unacknowledgedWarning = warningAlerts.filter(alert => {
                    const alertType = alert.message.includes('turbidity') ? 'turbidity' : 
                                     alert.message.includes('TDS') ? 'tds' :
                                     alert.message.includes('pH') ? 'ph' : null;
                    
                    if (!alertType) return false;
                    
                    // Check if this alert type is acknowledged
                    const isAcknowledged = acknowledgedAlerts.has(alertType) || isSensorAcknowledged(alertType);
                    return !isAcknowledged;
                });
                
                const hasCritical = unacknowledgedCritical.length > 0;
                const hasWarning = unacknowledgedWarning.length > 0;
                if (!window.__alertsSound) { window.__alertsSound = { lastLevel: null, lastAt: 0 }; }
                const nowTs = Date.now();
                const level = hasCritical ? 'critical' : hasWarning ? 'warning' : null;
                if (level && (window.__alertsSound.lastLevel !== level || nowTs - window.__alertsSound.lastAt > 15000)) {
                    window.__alertsSound.lastLevel = level; window.__alertsSound.lastAt = nowTs;
                    playEnhancedAlertSound(level);
                    // Removed showVisualAlert(level) - no more toast notifications
                }
            } catch (_) {}

            container.innerHTML = activeAlerts.map(alert => {
                const alertType = alert.message.includes('turbidity') ? 'turbidity' : 
                                 alert.message.includes('TDS') ? 'tds' :
                                 alert.message.includes('pH') ? 'ph' : null;
                const alertLevel = alert.type === 'critical' ? 'danger' : 'warning';
                const alertKey = alertType ? `${alertType}_${alertLevel}` : null;
                const isUnacknowledged = alertKey && unacknowledgedAlerts.has(alertKey);
                const isAcknowledged = alertType && (acknowledgedAlerts.has(alertType) || isSensorAcknowledged(alertType));
                const shouldShowAcknowledge = alertType ? shouldShowAcknowledgeButton(alertType, alertLevel) : false;
                
                return `
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
                                        ${isAcknowledged ? `<span class="px-2 py-1 text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded-full"><i class="fas fa-check mr-1"></i>Acknowledged (5h)</span>
                                        <button title="Reset acknowledgment" onclick="clearAcknowledgment('${alertType}')" class="ml-2 px-2 py-0.5 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600">
                                            Reset
                                        </button>` : ''}
                                    </div>
                                    <p class="text-gray-900 dark:text-white">${alert.message}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                        <i class="fas fa-clock mr-1"></i>${formatDate(alert.time)}
                                    </p>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                                ${alertType && shouldShowAcknowledge ? `
                                    <button onclick="showAcknowledgmentModal(${JSON.stringify(alert).replace(/"/g, '&quot;')})" 
                                            class="px-3 py-1 text-sm bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition-colors">
                                        <i class="fas fa-check mr-1"></i>Acknowledge
                                    </button>
                                ` : ''}
                                <button onclick="dismissAlert('${alert.time}')" class="p-2 text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300 transition-colors">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
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

        // Refresh acknowledgment reports
        async function refreshAcknowledgmentReports() {
            try {
                const response = await fetch('../../api/get_acknowledgments.php?limit=1000', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = await response.json();
                if (data.success && data.data) {
                    acknowledgmentReports = data.data;
                    currentAckPage = 1; // Reset to first page
                    renderAcknowledgmentReports();
                }
            } catch (error) {
                console.error('Error loading acknowledgment reports:', error);
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
                    },
                    cache: 'no-store'
                });
                
                const data = await response.json();
                if (data.success && data.data) {
                    document.getElementById('totalAcknowledged').textContent = data.data.total_acknowledged || 0;
                    document.getElementById('todayAcknowledged').textContent = data.data.today_acknowledged || 0;
                    console.log('Acknowledgment stats loaded:', data.data);
                    
                    // Show warning if there's a database error
                    if (data.error) {
                        console.warn('Database connection issue:', data.error);
                        document.getElementById('dbStatusIndicator').style.display = 'block';
                    } else {
                        document.getElementById('dbStatusIndicator').style.display = 'none';
                    }
                } else {
                    console.error('Failed to load acknowledgment stats:', data);
                    document.getElementById('totalAcknowledged').textContent = '0';
                    document.getElementById('todayAcknowledged').textContent = '0';
                }
            } catch (error) {
                console.error('Error loading acknowledgment stats:', error);
                document.getElementById('totalAcknowledged').textContent = '0';
                document.getElementById('todayAcknowledged').textContent = '0';
            }
        }

        // Render acknowledgment reports table
        function renderAcknowledgmentReports() {
            const tableBody = document.getElementById('acknowledgmentReportsTable');
            
            if (acknowledgmentReports.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                            <i class="fas fa-inbox text-2xl mb-2"></i>
                            <p>No acknowledgment reports found</p>
                        </td>
                    </tr>
                `;
                updateAcknowledgmentPaginationInfo(0);
                return;
            }
            
            // Calculate pagination
            const totalItems = acknowledgmentReports.length;
            const totalPages = Math.ceil(totalItems / ackItemsPerPage);
            
            // Ensure current page is valid
            if (currentAckPage > totalPages) {
                currentAckPage = totalPages || 1;
            }
            
            // Calculate pagination
            const startIndex = (currentAckPage - 1) * ackItemsPerPage;
            const endIndex = Math.min(startIndex + ackItemsPerPage, totalItems);
            const currentReports = acknowledgmentReports.slice(startIndex, endIndex);
            
            // Update pagination info
            updateAcknowledgmentPaginationInfo(totalItems, startIndex, endIndex);
            
            // Update pagination buttons
            updateAcknowledgmentPaginationButtons(totalPages);
            
            tableBody.innerHTML = currentReports.map(report => {
                const alertType = report.alert_type;
                const typeIcon = alertType === 'turbidity' ? 'fas fa-filter text-blue-500' :
                                alertType === 'tds' ? 'fas fa-flask text-emerald-500' :
                                alertType === 'ph' ? 'fas fa-vial text-purple-500' : 'fas fa-thermometer-half text-red-500';
                
                const typeBadge = alertType === 'turbidity' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' :
                                 alertType === 'tds' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200' :
                                 alertType === 'ph' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                
                return `
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">${formatDate(report.acknowledged_at)}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center space-x-2">
                                <i class="${typeIcon}"></i>
                                <span class="px-2 py-1 text-xs rounded-full ${typeBadge}">${alertType.toUpperCase()}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">${report.action_taken}</td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">${report.responsible_person}</td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">${report.details || 'No additional details'}</td>
                    </tr>
                `;
            }).join('');
        }

        // Update acknowledgment pagination info
        function updateAcknowledgmentPaginationInfo(totalItems, startIndex = 0, endIndex = 0) {
            document.getElementById('ackShowingStart').textContent = totalItems > 0 ? startIndex + 1 : 0;
            document.getElementById('ackShowingEnd').textContent = endIndex;
            document.getElementById('ackTotalItems').textContent = totalItems;
            document.getElementById('totalAcknowledgmentCount').textContent = totalItems;
        }

        // Update acknowledgment pagination buttons
        function updateAcknowledgmentPaginationButtons(totalPages) {
            const prevBtn = document.getElementById('ackPrevPage');
            const nextBtn = document.getElementById('ackNextPage');
            const pageNumbersContainer = document.getElementById('ackPageNumbers');
            
            // Update Previous/Next buttons
            prevBtn.disabled = currentAckPage <= 1;
            nextBtn.disabled = currentAckPage >= totalPages;
            
            // Generate page numbers
            let pageNumbersHTML = '';
            const maxVisiblePages = 5;
            
            if (totalPages <= maxVisiblePages) {
                // Show all pages if total is small
                for (let i = 1; i <= totalPages; i++) {
                    pageNumbersHTML += createAcknowledgmentPageNumberButton(i, i === currentAckPage);
                }
            } else {
                // Show smart pagination with ellipsis
                if (currentAckPage <= 3) {
                    // Show first 3 pages + ellipsis + last page
                    for (let i = 1; i <= 3; i++) {
                        pageNumbersHTML += createAcknowledgmentPageNumberButton(i, i === currentAckPage);
                    }
                    pageNumbersHTML += '<span class="px-2 py-1 text-gray-500">...</span>';
                    pageNumbersHTML += createAcknowledgmentPageNumberButton(totalPages, false);
                } else if (currentAckPage >= totalPages - 2) {
                    // Show first page + ellipsis + last 3 pages
                    pageNumbersHTML += createAcknowledgmentPageNumberButton(1, false);
                    pageNumbersHTML += '<span class="px-2 py-1 text-gray-500">...</span>';
                    for (let i = totalPages - 2; i <= totalPages; i++) {
                        pageNumbersHTML += createAcknowledgmentPageNumberButton(i, i === currentAckPage);
                    }
                } else {
                    // Show first page + ellipsis + current page + ellipsis + last page
                    pageNumbersHTML += createAcknowledgmentPageNumberButton(1, false);
                    pageNumbersHTML += '<span class="px-2 py-1 text-gray-500">...</span>';
                    for (let i = currentAckPage - 1; i <= currentAckPage + 1; i++) {
                        pageNumbersHTML += createAcknowledgmentPageNumberButton(i, i === currentAckPage);
                    }
                    pageNumbersHTML += '<span class="px-2 py-1 text-gray-500">...</span>';
                    pageNumbersHTML += createAcknowledgmentPageNumberButton(totalPages, false);
                }
            }
            
            pageNumbersContainer.innerHTML = pageNumbersHTML;
        }

        // Create acknowledgment page number button
        function createAcknowledgmentPageNumberButton(pageNum, isActive) {
            return `
                <button onclick="goToAcknowledgmentPage(${pageNum})" 
                        class="px-3 py-2 text-sm rounded-lg transition-colors ${
                            isActive 
                                ? 'bg-blue-500 text-white' 
                                : 'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300'
                        }">
                    ${pageNum}
                </button>
            `;
        }

        // Go to acknowledgment page
        function goToAcknowledgmentPage(page) {
            currentAckPage = page;
            renderAcknowledgmentReports();
        }

        // Export acknowledgment reports to CSV
        function exportAcknowledgmentReports() {
            if (acknowledgmentReports.length === 0) {
                showNotification('No acknowledgment reports to export', 'info');
                return;
            }

            // Prepare CSV content
            const headers = ['Time', 'Alert Type', 'Action Taken', 'Responsible Person', 'Details', 'Acknowledged At'];
            const csvContent = [
                headers.join(','),
                ...acknowledgmentReports.map(report => [
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
                
                showNotification(`Exported ${acknowledgmentReports.length} acknowledgment reports`, 'success');
            } else {
                showNotification('Export not supported in this browser', 'error');
            }
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
        document.getElementById('refreshAcknowledgmentReports').addEventListener('click', refreshAcknowledgmentReports);
        document.getElementById('exportAcknowledgmentReports').addEventListener('click', exportAcknowledgmentReports);
        
        // Acknowledgment pagination event listeners
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
        document.getElementById('clearAllAlerts').addEventListener('click', () => {
            showClearAllModal();
        });
        document.getElementById('acknowledgeAll').addEventListener('click', () => {
            // Get currently visible alerts from the DOM instead of alertHistory
            const activeAlertsContainer = document.getElementById('activeAlertsContainer');
            const alertElements = activeAlertsContainer.querySelectorAll('.alert-card');
            
            const acknowledgmentAlerts = [];
            
            alertElements.forEach(alertElement => {
                const acknowledgeButton = alertElement.querySelector('button[onclick*="showAcknowledgmentModal"]');
                
                // Only include alerts that have an acknowledge button (not already acknowledged)
                if (acknowledgeButton && !acknowledgeButton.disabled) {
                    // Extract alert data from the DOM element
                    const alertMessage = alertElement.querySelector('p.text-gray-900').textContent;
                    const parameterElement = alertElement.querySelector('span.font-semibold');
                    const valueElement = parameterElement?.nextElementSibling;
                    
                    // Determine alert type from message
                    let alertType = null;
                    if (alertMessage.includes('turbidity')) alertType = 'turbidity';
                    else if (alertMessage.includes('TDS')) alertType = 'tds';
                    else if (alertMessage.includes('pH')) alertType = 'ph';
                    
                    // Determine alert level
                    const alertLevel = alertMessage.includes('High') || alertMessage.includes('Critical') ? 'critical' : 'warning';
                    
                    // Only include turbidity, TDS, and pH alerts
                    if (alertType && (alertType === 'turbidity' || alertType === 'tds' || alertType === 'ph')) {
                        acknowledgmentAlerts.push({
                            type: alertLevel,
                            parameter: alertType,
                            message: alertMessage,
                            value: valueElement?.textContent || '0',
                            timestamp: new Date().toISOString()
                        });
                    }
                }
            });

            if (acknowledgmentAlerts.length === 0) {
                showNotification('No unacknowledged alerts available for acknowledgment', 'info');
                return;
            }

            console.log(`Acknowledging ${acknowledgmentAlerts.length} visible alerts:`, acknowledgmentAlerts);
            
            // Show bulk acknowledgment modal
            showBulkAcknowledgmentModal(acknowledgmentAlerts);
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

        // SSE connection (shared hosting friendly)
        function connectAckSSE() {
            try {
                if (ackEvtSrc) { try { ackEvtSrc.close(); } catch(_){} }
                ackEvtSrc = new EventSource('../../api/ack_events.php');
                let sseReady = false;
                const sseFallbackTimer = setTimeout(() => {
                    if (!sseReady) {
                        if (!window.__ackPoll) {
                            window.__ackPoll = setInterval(() => {
                                loadAcknowledgedAlerts();
                                loadAcknowledgmentStats();
                                refreshAcknowledgmentReports();
                            }, 3000);
                        }
                    }
                }, 6000);
                ackEvtSrc.onopen = () => { sseReady = true; if (window.__ackPoll) { clearInterval(window.__ackPoll); window.__ackPoll = null; } };
                ackEvtSrc.addEventListener('ack', () => {
                    loadAcknowledgedAlerts();
                    loadAcknowledgmentStats();
                    refreshAcknowledgmentReports();
                });
                ackEvtSrc.onerror = () => { try { ackEvtSrc.close(); } catch(_){}; setTimeout(connectAckSSE, 3000); };
            } catch (_) { setTimeout(connectAckSSE, 5000); }
        }

        async function initialize() {
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
            
            // Then fetch data and update UI
            fetchData();
            loadAcknowledgmentStats();
            refreshAcknowledgmentReports();
            connectAckSSE();
        }
        
        initialize();
        
        setInterval(fetchData, 5000);
        const ACK_POLL_INTERVAL_MS = 10000; // faster cross-device reflection
        setInterval(() => {
            clearExpiredAcknowledgments();
            loadAcknowledgedAlerts(); // Reload acknowledgments periodically
            loadAcknowledgmentStats();
            refreshAcknowledgmentReports();
        }, ACK_POLL_INTERVAL_MS);

        // Cross-tab sync: reflect acks instantly across browser tabs on same device
        window.addEventListener('storage', (e) => {
            if (e.key === ACK_STORAGE_KEY || e.key === ACK_RESET_KEY) {
                try {
                    const localAcks = readAckStorage();
                    Object.keys(localAcks).forEach(sensorType => acknowledgedAlerts.add(sensorType));
                } catch (_) {}
                updateActiveAlerts(alertHistory.filter(a => a.type === 'critical' || a.type === 'warning'));
                refreshAcknowledgmentReports();
                loadAcknowledgmentStats();
            }
        });

        // Enhanced sound alert function
        function playEnhancedAlertSound(level) {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                
                if (level === 'critical') {
                    // Critical alert: Multiple loud beeps with higher volume and longer duration
                    playAlertSequence(ctx, [
                        { freq: 1000, duration: 500, volume: 0.8 },
                        { freq: 800, duration: 500, volume: 0.8 },
                        { freq: 1000, duration: 500, volume: 0.8 },
                        { freq: 800, duration: 500, volume: 0.8 },
                        { freq: 1200, duration: 300, volume: 0.8 },
                        { freq: 1000, duration: 300, volume: 0.8 },
                        { freq: 1200, duration: 300, volume: 0.8 },
                        { freq: 1000, duration: 800, volume: 0.8 }
                    ]);
                } else if (level === 'warning') {
                    // Warning alert: Multiple beeps with higher volume and longer duration
                    playAlertSequence(ctx, [
                        { freq: 1200, duration: 600, volume: 0.6 },
                        { freq: 900, duration: 600, volume: 0.6 },
                        { freq: 1200, duration: 600, volume: 0.6 },
                        { freq: 900, duration: 600, volume: 0.6 },
                        { freq: 1200, duration: 1000, volume: 0.6 }
                    ]);
                }
            } catch (error) {
                console.error('Error playing alert sound:', error);
            }
        }

        function playAlertSequence(ctx, sequence) {
            let delay = 0;
            sequence.forEach((note, index) => {
                setTimeout(() => {
                    const oscillator = ctx.createOscillator();
                    const gainNode = ctx.createGain();
                    
                    oscillator.type = 'sine';
                    oscillator.frequency.value = note.freq;
                    gainNode.gain.value = note.volume;
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(ctx.destination);
                    
                    oscillator.start();
                    oscillator.stop(ctx.currentTime + note.duration / 1000);
                }, delay);
                delay += note.duration + 100; // Longer gap between notes for better distinction
            });
        }

        // Visual alert function
        function showVisualAlert(level) {
            const alertElement = document.createElement('div');
            alertElement.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transform transition-all duration-300 ${
                level === 'critical' ? 'bg-red-500 text-white animate-pulse' : 
                'bg-yellow-500 text-white animate-bounce'
            }`;
            alertElement.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${level === 'critical' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle'} mr-2 text-xl"></i>
                    <span class="font-bold text-lg">${level === 'critical' ? 'CRITICAL ALERT' : 'WARNING ALERT'}</span>
                </div>
                <div class="mt-2 text-sm">Water quality issue detected!</div>
            `;
            
            document.body.appendChild(alertElement);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                alertElement.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (alertElement.parentNode) {
                        alertElement.parentNode.removeChild(alertElement);
                    }
                }, 300);
            }, 5000);
        }
    </script>
</body>
</html>

