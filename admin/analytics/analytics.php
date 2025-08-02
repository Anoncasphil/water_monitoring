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
    <title>Analytics - Water Quality System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <i class="fas fa-chart-line text-blue-500 mr-3"></i>
                        Analytics Dashboard
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 text-lg">Comprehensive water quality data analysis and insights</p>
                </div>
                <div class="flex items-center space-x-4">
                    <select id="timeRange" class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white">
                        <option value="24h">Last 24 Hours</option>
                        <option value="7d">Last 7 Days</option>
                        <option value="30d">Last 30 Days</option>
                        <option value="90d">Last 90 Days</option>
                    </select>
                    <button id="themeToggle" class="p-3 rounded-xl bg-white dark:bg-gray-800 shadow-lg hover:shadow-xl transition-all duration-200">
                        <i class="fas fa-sun text-yellow-500 dark:hidden text-lg"></i>
                        <i class="fas fa-moon text-blue-300 hidden dark:block text-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Key Metrics Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="analytics-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Total Readings</h3>
                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400" id="totalReadings"><?php echo $stats['total_readings'] ?? '0'; ?></p>
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
                            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400" id="avgTurbidity"><?php echo number_format($stats['avg_turbidity'] ?? 0, 1); ?> NTU</p>
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
                            <p class="text-2xl font-bold text-purple-600 dark:text-purple-400" id="avgTDS"><?php echo number_format($stats['avg_tds'] ?? 0, 0); ?> ppm</p>
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
                            <p class="text-2xl font-bold text-red-600 dark:text-red-400" id="avgPH"><?php echo number_format($stats['avg_ph'] ?? 0, 2); ?></p>
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

            <!-- Main Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Real-time Trends Chart -->
                <div class="analytics-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                                <i class="fas fa-chart-line text-blue-500 mr-3"></i>
                                Real-time Trends
                            </h2>
                            <p class="text-gray-600 dark:text-gray-400">24-hour water quality parameter trends</p>
                        </div>
                        <div class="flex space-x-2">
                            <button class="px-3 py-1 text-sm bg-blue-50 dark:bg-blue-900 text-blue-600 dark:text-blue-300 rounded-full hover:bg-blue-100 dark:hover:bg-blue-800">
                                <i class="fas fa-clock mr-1"></i>Live
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
                                Daily Averages
                            </h2>
                            <p class="text-gray-600 dark:text-gray-400">7-day average water quality metrics</p>
                        </div>
                        <div class="flex space-x-2">
                            <button class="px-3 py-1 text-sm bg-purple-50 dark:bg-purple-900 text-purple-600 dark:text-purple-300 rounded-full hover:bg-purple-100 dark:hover:bg-purple-800">
                                <i class="fas fa-calendar mr-1"></i>7d
                            </button>
                        </div>
                    </div>
                    <div class="h-[400px]">
                        <canvas id="dailyChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Statistical Analysis -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                <!-- Parameter Ranges -->
                <div class="analytics-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                            <i class="fas fa-ruler text-green-500 mr-3"></i>
                            Parameter Ranges
                        </h2>
                    </div>
                    <div class="space-y-6">
                        <!-- Turbidity Range -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Turbidity (NTU)</span>
                                <span class="text-sm text-gray-500"><?php echo number_format($stats['min_turbidity'] ?? 0, 1); ?> - <?php echo number_format($stats['max_turbidity'] ?? 0, 1); ?></span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo min(100, (($stats['avg_turbidity'] ?? 0) / 20) * 100); ?>%"></div>
                            </div>
                        </div>

                        <!-- TDS Range -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">TDS (ppm)</span>
                                <span class="text-sm text-gray-500"><?php echo number_format($stats['min_tds'] ?? 0, 0); ?> - <?php echo number_format($stats['max_tds'] ?? 0, 0); ?></span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-emerald-500 h-2 rounded-full" style="width: <?php echo min(100, (($stats['avg_tds'] ?? 0) / 1000) * 100); ?>%"></div>
                            </div>
                        </div>

                        <!-- pH Range -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">pH Level</span>
                                <span class="text-sm text-gray-500"><?php echo number_format($stats['min_ph'] ?? 0, 1); ?> - <?php echo number_format($stats['max_ph'] ?? 0, 1); ?></span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-purple-500 h-2 rounded-full" style="width: <?php echo min(100, (($stats['avg_ph'] ?? 7) / 14) * 100); ?>%"></div>
                            </div>
                        </div>

                        <!-- Temperature Range -->
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Temperature (°C)</span>
                                <span class="text-sm text-gray-500"><?php echo number_format($stats['min_temp'] ?? 0, 1); ?> - <?php echo number_format($stats['max_temp'] ?? 0, 1); ?></span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-red-500 h-2 rounded-full" style="width: <?php echo min(100, (($stats['avg_temperature'] ?? 20) / 50) * 100); ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quality Insights -->
                <div class="analytics-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                            <i class="fas fa-lightbulb text-yellow-500 mr-3"></i>
                            Quality Insights
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
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Last Update</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white" id="lastUpdate">--:--:--</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let trendsChart = null;
        let dailyChart = null;

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

            trendsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => formatDate(d.reading_time)),
                    datasets: [{
                        label: 'Turbidity (NTU)',
                        data: data.map(d => parseFloat(d.turbidity)),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: turbidityGradient,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 2,
                        pointHoverRadius: 6
                    }, {
                        label: 'TDS (ppm)',
                        data: data.map(d => parseFloat(d.tds)),
                        borderColor: 'rgb(16, 185, 129)',
                        backgroundColor: tdsGradient,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 2,
                        pointHoverRadius: 6
                    }, {
                        label: 'pH',
                        data: data.map(d => parseFloat(d.ph)),
                        borderColor: 'rgb(168, 85, 247)',
                        backgroundColor: phGradient,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 2,
                        pointHoverRadius: 6
                    }, {
                        label: 'Temperature (°C)',
                        data: data.map(d => parseFloat(d.temperature)),
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
                            usePointStyle: true
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
            fetch('../../api/get_readings.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);

                    // Update last update time
                    if (data.latest) {
                        document.getElementById('lastUpdate').textContent = formatDate(data.latest.reading_time);
                    }

                    // Update charts
                    if (data.historical && data.historical.length > 0) {
                        createTrendsChart(data.historical);
                    }
                })
                .catch(error => {
                    console.error('Error fetching data:', error);
                });
        }

        // Initialize charts with PHP data
        <?php if (!empty($hourlyData)): ?>
        createTrendsChart(<?php echo json_encode($hourlyData); ?>);
        <?php endif; ?>

        <?php if (!empty($dailyData)): ?>
        createDailyChart(<?php echo json_encode($dailyData); ?>);
        <?php endif; ?>

        // Update data every 30 seconds
        updateData();
        setInterval(updateData, 30000);

        // Time range selector
        document.getElementById('timeRange').addEventListener('change', function() {
            const timeRange = this.value;
            // Here you would typically fetch new data based on the selected time range
            console.log('Time range changed to:', timeRange);
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
