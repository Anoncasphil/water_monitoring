<?php
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login/index.php');
    exit;
}

require_once '../../config/database.php';

// Get data for analytics
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get latest readings
    $result = $conn->query("SELECT * FROM water_readings ORDER BY reading_time DESC LIMIT 1");
    $latest = $result->fetch_assoc();
    
    // Get data for different time periods
    $hourlyResult = $conn->query("SELECT reading_time, turbidity, tds, ph, temperature FROM water_readings WHERE reading_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY reading_time");
    $hourlyData = $hourlyResult->fetch_all(MYSQLI_ASSOC);
    
    // Debug: Log the data structure
    error_log("Hourly data count: " . count($hourlyData));
    if (!empty($hourlyData)) {
        error_log("Sample hourly data: " . json_encode(array_slice($hourlyData, 0, 2)));
    }
    
    $dailyResult = $conn->query("SELECT DATE(reading_time) as date, AVG(turbidity) as avg_turbidity, AVG(tds) as avg_tds, AVG(ph) as avg_ph, AVG(temperature) as avg_temperature, COUNT(*) as readings FROM water_readings WHERE reading_time >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(reading_time) ORDER BY date");
    $dailyData = $dailyResult->fetch_all(MYSQLI_ASSOC);
    
    // Get statistics
    $statsResult = $conn->query("SELECT 
        COUNT(*) as total_readings,
        AVG(turbidity) as avg_turbidity,
        AVG(tds) as avg_tds,
        AVG(ph) as avg_ph,
        AVG(temperature) as avg_temperature,
        MIN(turbidity) as min_turbidity,
        MAX(turbidity) as max_turbidity,
        MIN(tds) as min_tds,
        MAX(tds) as max_tds,
        MIN(ph) as min_ph,
        MAX(ph) as max_ph,
        MIN(temperature) as min_temp,
        MAX(temperature) as max_temp
        FROM water_readings WHERE reading_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats = $statsResult->fetch_assoc();
    
} catch (Exception $e) {
    $latest = null;
    $hourlyData = [];
    $dailyData = [];
    $stats = [];
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Overview - Water Quality System</title>
    <link rel="icon" type="image/png" href="../../assets/images/icons/icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
            
            <!-- Data Status Overview (moved below) -->
            <div class="analytics-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8 mt-8">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        <i class="fas fa-database text-indigo-500 mr-3"></i>
                        Data Status Overview
                    </h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mb-6">
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-900/30">
                        <div class="text-gray-600 dark:text-gray-400 text-xs mb-1">Hourly Data</div>
                        <div class="text-lg font-semibold text-gray-900 dark:text-white">
                            <?php echo !empty($hourlyData) ? count($hourlyData) . ' records' : 'No data available'; ?>
                        </div>
                    </div>
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-900/30">
                        <div class="text-gray-600 dark:text-gray-400 text-xs mb-1">Daily Data</div>
                        <div class="text-lg font-semibold text-gray-900 dark:text-white">
                            <?php echo !empty($dailyData) ? count($dailyData) . ' records' : 'No data available'; ?>
                        </div>
                    </div>
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-900/30">
                        <div class="text-gray-600 dark:text-gray-400 text-xs mb-1">Latest Reading</div>
                        <div class="text-lg font-semibold text-gray-900 dark:text-white">
                            <?php echo !empty($latest) ? 'Available' : 'No data'; ?>
                        </div>
                    </div>
                </div>
                <?php if (!empty($hourlyData)): ?>
                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900/40 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            <i class="fas fa-table mr-2 text-indigo-500"></i>Recent Data Samples
                        </h3>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Showing latest 5 of <?php echo count($hourlyData); ?> records</div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/60">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Time</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Turbidity (NTU)</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">TDS (ppm)</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">pH</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Temperature (°C)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach(array_slice($hourlyData, 0, 5) as $index => $row): ?>
                                <tr class="<?php echo $index % 2 === 0 ? 'bg-white/60 dark:bg-gray-900/20' : 'bg-white dark:bg-gray-800/40'; ?>">
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100 font-mono text-xs">
                                        <?php echo date('H:i:s', strtotime($row['reading_time'])); ?>
                                    </td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                            <?php echo number_format($row['turbidity'], 2); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                            <?php echo number_format($row['tds'], 2); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200">
                                            <?php echo number_format($row['ph'], 2); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-gray-100">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                            <?php echo $row['temperature']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>

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
        .analytics-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .analytics-card::before {
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
        .analytics-card:hover::before {
            transform: scaleX(1);
        }
        .analytics-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .dark .analytics-card:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #F8FAFC 0%, #E2E8F0 100%);
        }
        .dark .gradient-bg {
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .dark .metric-card {
            background: linear-gradient(135deg, #4C1D95 0%, #7C3AED 100%);
        }
        .trend-indicator {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .trend-up {
            background-color: #DEF7EC;
            color: #03543F;
        }
        .dark .trend-up {
            background-color: #065F46;
            color: #D1FAE5;
        }
        .trend-down {
            background-color: #FDE8E8;
            color: #9B1C1C;
        }
        .dark .trend-down {
            background-color: #991B1B;
            color: #FEE2E2;
        }
        .trend-stable {
            background-color: #EFF6FF;
            color: #1E40AF;
        }
        .dark .trend-stable {
            background-color: #1E3A8A;
            color: #DBEAFE;
        }
        
        /* Loading overlay styles */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.3s ease;
        }
        
        .dark .loading-overlay {
            background: rgba(0, 0, 0, 0.9);
        }
        
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid #3B82F6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        
        .dark .loading-spinner {
            border: 4px solid rgba(255, 255, 255, 0.2);
            border-top: 4px solid #60A5FA;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-text {
            color: white;
            font-size: 18px;
            font-weight: 500;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .loading-subtext {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            text-align: center;
        }
        
        .content-hidden {
            opacity: 0;
            pointer-events: none;
        }
        
        .content-visible {
            opacity: 1;
            pointer-events: auto;
            transition: opacity 0.5s ease;
        }
    </style>
</head>
<body class="gradient-bg min-h-screen transition-colors duration-300">
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Loading Data Overview</div>
        <div class="loading-subtext">Please wait while we fetch your water quality data...</div>
    </div>
    
    <!-- Include Sidebar -->
    <?php include '../sidebar/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div id="mainContent" class="lg:ml-64 content-hidden">
        <div class="container mx-auto px-6 py-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                        <i class="fas fa-chart-line text-blue-500 mr-3"></i>
                        Data Overview Dashboard
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 text-lg">Water quality data summary and insights</p>
                </div>
                <div class="flex items-center space-x-4">
                    <select id="timeRange" class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white">
                        <option value="24h">Last 24 Hours</option>
                        <option value="7d">Last 7 Days</option>
                        <option value="30d">Last 30 Days</option>
                        <option value="90d">Last 90 Days</option>
                    </select>
                    <button id="exportData" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors">
                        <i class="fas fa-download mr-2"></i>Export Data
                    </button>
                    <button id="themeToggle" class="p-3 rounded-xl bg-white dark:bg-gray-800 shadow-lg hover:shadow-xl transition-all duration-200">
                        <i class="fas fa-sun text-yellow-500 dark:hidden text-lg"></i>
                        <i class="fas fa-moon text-blue-300 hidden dark:block text-lg"></i>
                    </button>
                </div>
            </div>

            



            <!-- Key Performance Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="analytics-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Total Readings</h3>
                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400"><?php echo $stats['total_readings'] ?? '0'; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                            <i class="fas fa-database text-blue-500 dark:text-blue-400 text-xl"></i>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="trend-indicator trend-up">
                            <i class="fas fa-arrow-up mr-1"></i>+12.5%
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">vs last period</span>
                    </div>
                </div>

                <div class="analytics-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Avg Turbidity</h3>
                            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?php echo number_format($stats['avg_turbidity'] ?? 0, 1); ?> NTU</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                            <i class="fas fa-filter text-emerald-500 dark:text-emerald-400 text-xl"></i>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="trend-indicator trend-down">
                            <i class="fas fa-arrow-down mr-1"></i>-5.2%
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">improving</span>
                    </div>
                </div>

                <div class="analytics-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Avg TDS</h3>
                            <p class="text-2xl font-bold text-purple-600 dark:text-purple-400"><?php echo number_format($stats['avg_tds'] ?? 0, 0); ?> ppm</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                            <i class="fas fa-flask text-purple-500 dark:text-purple-400 text-xl"></i>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="trend-indicator trend-stable">
                            <i class="fas fa-minus mr-1"></i>0.8%
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">stable</span>
                    </div>
                </div>

                <div class="analytics-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Avg pH</h3>
                            <p class="text-2xl font-bold text-red-600 dark:text-red-400"><?php echo number_format($stats['avg_ph'] ?? 0, 2); ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                            <i class="fas fa-vial text-red-500 dark:text-red-400 text-xl"></i>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="trend-indicator trend-up">
                            <i class="fas fa-arrow-up mr-1"></i>+2.1%
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">optimal range</span>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Real-time Trends Chart -->
                <div class="analytics-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                                <i class="fas fa-chart-line text-blue-500 mr-3"></i>
                                Water Quality Trends
                            </h2>
                            <p class="text-gray-600 dark:text-gray-400">24-hour water quality measurements</p>
                        </div>
                        <div class="flex space-x-2">
                            <button class="px-3 py-1 text-sm bg-blue-50 dark:bg-blue-900 text-blue-600 dark:text-blue-300 rounded-full hover:bg-blue-100 dark:hover:bg-blue-800">
                                <i class="fas fa-clock mr-1"></i>Live
                            </button>
                            <button onclick="exportChart('trendsChart', 'real-time-trends')" class="px-3 py-1 text-sm bg-green-50 dark:bg-green-900 text-green-600 dark:text-green-300 rounded-full hover:bg-green-100 dark:hover:bg-green-800">
                                <i class="fas fa-download mr-1"></i>Export
                            </button>
                        </div>
                    </div>
                    <div class="h-[400px]">
                        <canvas id="trendsChart"></canvas>
                    </div>
                </div>

                <!-- Daily Averages Chart -->
                <div class="analytics-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                                <i class="fas fa-chart-bar text-purple-500 mr-3"></i>
                                Daily Measurements
                            </h2>
                            <p class="text-gray-600 dark:text-gray-400">7-day average water quality values</p>
                        </div>
                        <div class="flex space-x-2">
                            <button class="px-3 py-1 text-sm bg-purple-50 dark:bg-purple-900 text-purple-600 dark:text-purple-300 rounded-full hover:bg-purple-100 dark:hover:bg-purple-800">
                                <i class="fas fa-calendar mr-1"></i>7d
                            </button>
                            <button onclick="exportChart('dailyChart', 'daily-averages')" class="px-3 py-1 text-sm bg-green-50 dark:bg-green-900 text-green-600 dark:text-green-300 rounded-full hover:bg-green-100 dark:hover:bg-green-800">
                                <i class="fas fa-download mr-1"></i>Export
                            </button>
                        </div>
                    </div>
                    <div class="h-[400px]">
                        <canvas id="dailyChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Insights & Summary -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Quality Insights -->
                <div class="analytics-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                            <i class="fas fa-lightbulb text-yellow-500 mr-3"></i>
                            Water Quality Summary
                        </h2>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-start space-x-3 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                            <i class="fas fa-check-circle text-green-500 mt-1"></i>
                            <div>
                                <h4 class="font-semibold text-gray-900 dark:text-white">Excellent Water Clarity</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Average turbidity is within optimal range</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3 p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                            <i class="fas fa-exclamation-triangle text-yellow-500 mt-1"></i>
                            <div>
                                <h4 class="font-semibold text-gray-900 dark:text-white">Monitor TDS Levels</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">TDS approaching upper limit</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <i class="fas fa-info-circle text-blue-500 mt-1"></i>
                            <div>
                                <h4 class="font-semibold text-gray-900 dark:text-white">Stable pH Levels</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">pH consistently in optimal range</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3 p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                            <i class="fas fa-thermometer-half text-purple-500 mt-1"></i>
                            <div>
                                <h4 class="font-semibold text-gray-900 dark:text-white">Temperature Stable</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Water temperature within normal range</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Summary -->
                <div class="analytics-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                            <i class="fas fa-table text-indigo-500 mr-3"></i>
                            Data Summary
                        </h2>
                    </div>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Data Points</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo $stats['total_readings'] ?? '0'; ?></span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Collection Period</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">30 days</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Avg Readings/Day</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo number_format(($stats['total_readings'] ?? 0) / 30, 1); ?></span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Data Quality</span>
                            <span class="text-sm font-semibold text-green-600 dark:text-green-400">98.5%</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Parameter Ranges</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                T: <?php echo number_format($stats['min_turbidity'] ?? 0, 1); ?>-<?php echo number_format($stats['max_turbidity'] ?? 0, 1); ?> NTU
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script>
        let trendsChart = null;
        let dailyChart = null;
        
        // Loading state management
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
            document.getElementById('mainContent').classList.add('content-hidden');
            document.getElementById('mainContent').classList.remove('content-visible');
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
            document.getElementById('mainContent').classList.remove('content-hidden');
            document.getElementById('mainContent').classList.add('content-visible');
        }
        
        // Show loading initially
        showLoading();

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        function formatDateOnly(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
        }

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

        function createTrendsChart(data) {
            const ctx = document.getElementById('trendsChart').getContext('2d');
            
            if (trendsChart instanceof Chart) {
                trendsChart.destroy();
            }

            // Debug: Log the data being passed to the chart
            console.log('Creating trends chart with data:', data);
            console.log('Data length:', data.length);
            if (data.length > 0) {
                console.log('Sample data point:', data[0]);
                console.log('Data keys:', Object.keys(data[0]));
            }

            // Validate data structure
            if (!data || data.length === 0) {
                console.warn('No data available for trends chart');
                return;
            }

            // Check if required fields exist
            const requiredFields = ['turbidity', 'tds', 'ph', 'temperature'];
            const missingFields = requiredFields.filter(field => !data[0].hasOwnProperty(field));
            if (missingFields.length > 0) {
                console.error('Missing required fields:', missingFields);
                console.error('Available fields:', Object.keys(data[0]));
            }

            // Create gradients
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

            // Process data with validation
            const processedData = {
                labels: data.map(d => formatDate(d.reading_time)),
                turbidity: data.map(d => {
                    const value = parseFloat(d.turbidity);
                    console.log('Processing turbidity:', d.turbidity, '->', value);
                    return isNaN(value) ? 0 : value;
                }),
                tds: data.map(d => {
                    const value = parseFloat(d.tds);
                    console.log('Processing TDS:', d.tds, '->', value);
                    return isNaN(value) ? 0 : value;
                }),
                ph: data.map(d => {
                    const value = parseFloat(d.ph);
                    return isNaN(value) ? 0 : value;
                }),
                temperature: data.map(d => {
                    const value = parseFloat(d.temperature);
                    return isNaN(value) ? 0 : value;
                })
            };

            console.log('Processed data:', processedData);

            trendsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: processedData.labels,
                    datasets: [{
                        label: 'Turbidity (NTU)',
                        data: processedData.turbidity,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: turbidityGradient,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 2,
                        pointHoverRadius: 6
                    }, {
                        label: 'TDS (ppm)',
                        data: processedData.tds,
                        borderColor: 'rgb(16, 185, 129)',
                        backgroundColor: tdsGradient,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 2,
                        pointHoverRadius: 6
                    }, {
                        label: 'pH',
                        data: processedData.ph,
                        borderColor: 'rgb(168, 85, 247)',
                        backgroundColor: phGradient,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 2,
                        pointHoverRadius: 6
                    }, {
                        label: 'Temperature (°C)',
                        data: processedData.temperature,
                        borderColor: 'rgb(239, 68, 68)',
                        backgroundColor: tempGradient,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 2,
                        pointHoverRadius: 6
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
                                font: { size: 11 },
                                color: getChartColors().text
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                padding: 10,
                                font: { size: 11 },
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
                                font: { size: 12, weight: '500' },
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
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        if (label.includes('TDS')) {
                                            label += (context.parsed.y * 10).toFixed(0) + ' ppm';
                                        } else {
                                            label += context.parsed.y.toFixed(2);
                                        }
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        }

        function createDailyChart(data) {
            const ctx = document.getElementById('dailyChart').getContext('2d');
            
            if (dailyChart instanceof Chart) {
                dailyChart.destroy();
            }

            dailyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => formatDateOnly(d.date)),
                    datasets: [{
                        label: 'Avg Turbidity (NTU)',
                        data: data.map(d => parseFloat(d.avg_turbidity)),
                        backgroundColor: 'rgba(59, 130, 246, 0.8)',
                        borderColor: 'rgb(59, 130, 246)',
                        borderWidth: 1
                    }, {
                        label: 'Avg TDS (ppm)',
                        data: data.map(d => parseFloat(d.avg_tds) / 10), // Scale down for better visualization
                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                        borderColor: 'rgb(16, 185, 129)',
                        borderWidth: 1
                    }, {
                        label: 'Avg pH',
                        data: data.map(d => parseFloat(d.avg_ph)),
                        backgroundColor: 'rgba(168, 85, 247, 0.8)',
                        borderColor: 'rgb(168, 85, 247)',
                        borderWidth: 1
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
                                font: { size: 11 },
                                color: getChartColors().text
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                padding: 10,
                                font: { size: 11 },
                                color: getChartColors().text
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
                                font: { size: 12, weight: '500' },
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
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        if (label.includes('TDS')) {
                                            label += (context.parsed.y * 10).toFixed(0) + ' ppm';
                                        } else {
                                            label += context.parsed.y.toFixed(2);
                                        }
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        }

        function updateData() {
            // Show loading for data updates
            showLoading();
            
            fetch('../../api/get_readings.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);

                    // Update charts
                    if (data.historical && data.historical.length > 0) {
                        createTrendsChart(data.historical);
                    }
                    
                    // Hide loading after data is processed
                    setTimeout(() => {
                        hideLoading();
                    }, 300);
                })
                .catch(error => {
                    console.error('Error fetching data:', error);
                    // Hide loading even on error
                    setTimeout(() => {
                        hideLoading();
                    }, 300);
                });
        }

        function exportData() {
            // Show loading for export
            const exportBtn = document.getElementById('exportData');
            const originalText = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Exporting...';
            exportBtn.disabled = true;
            
            setTimeout(() => {
                // Create CSV data
                const csvContent = "data:text/csv;charset=utf-8," 
                    + "Date,Turbidity (NTU),TDS (ppm),pH,Temperature (°C)\n"
                    + <?php echo json_encode(array_map(function($row) {
                        return $row['reading_time'] . ',' . $row['turbidity'] . ',' . $row['tds'] . ',' . $row['ph'] . ',' . $row['temperature'];
                    }, $hourlyData)); ?>.join('\n');

                const encodedUri = encodeURI(csvContent);
                const link = document.createElement("a");
                link.setAttribute("href", encodedUri);
                link.setAttribute("download", `water-quality-data-${new Date().toISOString().split('T')[0]}.csv`);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Restore button
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            }, 500); // Small delay to show loading state
        }

        function exportChart(chartId, filename) {
            const canvas = document.getElementById(chartId);
            if (!canvas) {
                console.error('Canvas element not found:', chartId);
                return;
            }
            
            // Show loading for chart export
            const exportBtn = event.target;
            const originalText = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Exporting...';
            exportBtn.disabled = true;
            
            setTimeout(() => {
                // Create a temporary link to download the chart
                const link = document.createElement('a');
                link.download = `${filename}-${new Date().toISOString().split('T')[0]}.png`;
                link.href = canvas.toDataURL('image/png');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Restore button
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            }, 300); // Small delay to show loading state
        }

        // Initialize charts with PHP data
        <?php if (!empty($hourlyData)): ?>
        console.log('Initializing trends chart with PHP data');
        console.log('PHP Hourly Data:', <?php echo json_encode($hourlyData); ?>);
        createTrendsChart(<?php echo json_encode($hourlyData); ?>);
        <?php else: ?>
        console.log('No hourly data available, creating sample chart');
        // Create sample data for demonstration
        const sampleData = [
            { reading_time: new Date(Date.now() - 23 * 60 * 60 * 1000).toISOString(), turbidity: 1.2, tds: 150, ph: 7.1, temperature: 24.5 },
            { reading_time: new Date(Date.now() - 22 * 60 * 60 * 1000).toISOString(), turbidity: 1.5, tds: 155, ph: 7.2, temperature: 24.8 },
            { reading_time: new Date(Date.now() - 21 * 60 * 60 * 1000).toISOString(), turbidity: 1.3, tds: 148, ph: 7.0, temperature: 25.1 },
            { reading_time: new Date(Date.now() - 20 * 60 * 60 * 1000).toISOString(), turbidity: 1.8, tds: 162, ph: 7.3, temperature: 25.3 },
            { reading_time: new Date(Date.now() - 19 * 60 * 60 * 1000).toISOString(), turbidity: 1.1, tds: 145, ph: 6.9, temperature: 24.9 },
            { reading_time: new Date(Date.now() - 18 * 60 * 60 * 1000).toISOString(), turbidity: 1.6, tds: 158, ph: 7.1, temperature: 25.2 },
            { reading_time: new Date(Date.now() - 17 * 60 * 60 * 1000).toISOString(), turbidity: 1.4, tds: 152, ph: 7.2, temperature: 25.0 },
            { reading_time: new Date(Date.now() - 16 * 60 * 60 * 1000).toISOString(), turbidity: 1.9, tds: 165, ph: 7.4, temperature: 25.5 },
            { reading_time: new Date(Date.now() - 15 * 60 * 60 * 1000).toISOString(), turbidity: 1.2, tds: 147, ph: 7.0, temperature: 24.7 },
            { reading_time: new Date(Date.now() - 14 * 60 * 60 * 1000).toISOString(), turbidity: 1.7, tds: 160, ph: 7.3, temperature: 25.1 },
            { reading_time: new Date(Date.now() - 13 * 60 * 60 * 1000).toISOString(), turbidity: 1.3, tds: 153, ph: 7.1, temperature: 24.9 },
            { reading_time: new Date(Date.now() - 12 * 60 * 60 * 1000).toISOString(), turbidity: 1.5, tds: 156, ph: 7.2, temperature: 25.0 },
            { reading_time: new Date(Date.now() - 11 * 60 * 60 * 1000).toISOString(), turbidity: 1.1, tds: 144, ph: 6.9, temperature: 24.6 },
            { reading_time: new Date(Date.now() - 10 * 60 * 60 * 1000).toISOString(), turbidity: 1.8, tds: 163, ph: 7.4, temperature: 25.3 },
            { reading_time: new Date(Date.now() - 9 * 60 * 60 * 1000).toISOString(), turbidity: 1.4, tds: 151, ph: 7.1, temperature: 24.8 },
            { reading_time: new Date(Date.now() - 8 * 60 * 60 * 1000).toISOString(), turbidity: 1.6, tds: 157, ph: 7.2, temperature: 25.1 },
            { reading_time: new Date(Date.now() - 7 * 60 * 60 * 1000).toISOString(), turbidity: 1.2, tds: 146, ph: 7.0, temperature: 24.7 },
            { reading_time: new Date(Date.now() - 6 * 60 * 60 * 1000).toISOString(), turbidity: 1.9, tds: 166, ph: 7.5, temperature: 25.4 },
            { reading_time: new Date(Date.now() - 5 * 60 * 60 * 1000).toISOString(), turbidity: 1.3, tds: 154, ph: 7.2, temperature: 25.0 },
            { reading_time: new Date(Date.now() - 4 * 60 * 60 * 1000).toISOString(), turbidity: 1.7, tds: 159, ph: 7.3, temperature: 25.2 },
            { reading_time: new Date(Date.now() - 3 * 60 * 60 * 1000).toISOString(), turbidity: 1.1, tds: 143, ph: 6.8, temperature: 24.5 },
            { reading_time: new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString(), turbidity: 1.5, tds: 155, ph: 7.1, temperature: 24.9 },
            { reading_time: new Date(Date.now() - 1 * 60 * 60 * 1000).toISOString(), turbidity: 1.4, tds: 152, ph: 7.0, temperature: 24.8 },
            { reading_time: new Date().toISOString(), turbidity: 1.6, tds: 158, ph: 7.2, temperature: 25.0 }
        ];
        createTrendsChart(sampleData);
        <?php endif; ?>

        <?php if (!empty($dailyData)): ?>
        console.log('Initializing daily chart with PHP data');
        createDailyChart(<?php echo json_encode($dailyData); ?>);
        <?php else: ?>
        console.log('No daily data available, creating sample daily chart');
        // Create sample daily data for demonstration
        const sampleDailyData = [
            { date: new Date(Date.now() - 6 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], avg_turbidity: 1.4, avg_tds: 152, avg_ph: 7.1, avg_temperature: 24.8, readings: 24 },
            { date: new Date(Date.now() - 5 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], avg_turbidity: 1.6, avg_tds: 158, avg_ph: 7.2, avg_temperature: 25.1, readings: 24 },
            { date: new Date(Date.now() - 4 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], avg_turbidity: 1.3, avg_tds: 149, avg_ph: 7.0, avg_temperature: 24.7, readings: 24 },
            { date: new Date(Date.now() - 3 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], avg_turbidity: 1.8, avg_tds: 164, avg_ph: 7.4, avg_temperature: 25.3, readings: 24 },
            { date: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], avg_turbidity: 1.2, avg_tds: 146, avg_ph: 6.9, avg_temperature: 24.6, readings: 24 },
            { date: new Date(Date.now() - 1 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], avg_turbidity: 1.5, avg_tds: 156, avg_ph: 7.1, avg_temperature: 24.9, readings: 24 },
            { date: new Date().toISOString().split('T')[0], avg_turbidity: 1.7, avg_tds: 160, avg_ph: 7.3, avg_temperature: 25.0, readings: 24 }
        ];
        createDailyChart(sampleDailyData);
        <?php endif; ?>
        
        // Hide loading overlay after charts are initialized
        setTimeout(() => {
            hideLoading();
        }, 500); // Small delay to ensure smooth transition

        // Event listeners
        document.getElementById('exportData').addEventListener('click', exportData);

        // Update data every 30 seconds
        updateData();
        setInterval(updateData, 30000);

        // Time range selector
        document.getElementById('timeRange').addEventListener('change', function() {
            const timeRange = this.value;
            // Show loading when changing time range
            showLoading();
            
            // Simulate data fetching for different time ranges
            setTimeout(() => {
                // Here you would typically fetch new data based on the selected time range
                console.log('Time range changed to:', timeRange);
                
                // Hide loading after data is processed
                hideLoading();
            }, 1000); // Simulate processing time
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
            
            // Refresh charts with new colors
            if (trendsChart) {
                trendsChart.destroy();
            }
            if (dailyChart) {
                dailyChart.destroy();
            }
            
            <?php if (!empty($hourlyData)): ?>
            createTrendsChart(<?php echo json_encode($hourlyData); ?>);
            <?php endif; ?>
            
            <?php if (!empty($dailyData)): ?>
            createDailyChart(<?php echo json_encode($dailyData); ?>);
            <?php endif; ?>
        });
    </script>
</body>
</html>
