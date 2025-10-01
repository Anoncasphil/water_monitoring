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
    $result = $conn->query("SELECT * FROM water_readings ORDER BY reading_time DESC LIMIT 1 OFFSET 3");
    $latest = $result->fetch_assoc();
} catch (Exception $e) {
    $latest = null;
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
        .sensor-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .sensor-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3B82F6, #10B981, #8B5CF6, #EF4444);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        .sensor-card:hover::before {
            transform: scaleX(1);
        }
        .sensor-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .dark .sensor-card:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #F8FAFC 0%, #E2E8F0 100%);
        }
        .dark .gradient-bg {
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .value-display {
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Roboto Mono', monospace;
            font-weight: 600;
        }
        .quality-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
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
                        <i class="fas fa-tachometer-alt text-blue-500 mr-3"></i>
                        Water Quality Monitor
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 text-lg">Real-time sensor monitoring and quality assessment</p>
                </div>
                <div class="flex items-center space-x-6">
                    <div class="text-center">
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Last Updated</div>
                        <div class="text-lg font-semibold text-gray-900 dark:text-white" id="lastUpdate">--:--:--</div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="status-indicator bg-green-500"></div>
                        <span class="text-sm font-medium text-green-600 dark:text-green-400">System Online</span>
                    </div>
                    <button id="themeToggle" class="p-3 rounded-xl bg-white dark:bg-gray-800 shadow-lg hover:shadow-xl transition-all duration-200">
                        <i class="fas fa-sun text-yellow-500 dark:hidden text-lg"></i>
                        <i class="fas fa-moon text-blue-300 hidden dark:block text-lg"></i>
                    </button>
                </div>
            </div>
            
            <!-- Sensor Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-10">
                <!-- Turbidity Sensor -->
                <div class="sensor-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <div class="w-16 h-16 rounded-2xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mr-4">
                                <i class="fas fa-filter text-blue-500 dark:text-blue-400 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Turbidity</h3>
                                <p class="text-gray-500 dark:text-gray-400">Water Clarity</p>
                            </div>
                        </div>
                        <div class="quality-badge" id="turbidityQuality">--</div>
                    </div>
                    <div class="text-center mb-4">
                        <div class="value-display text-4xl text-gray-900 dark:text-white mb-2" id="turbidityValue">--</div>
                        <div class="text-lg text-gray-500 dark:text-gray-400 mb-2">NTU</div>
                        <div class="text-xs text-blue-500 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 px-3 py-1 rounded-full inline-block" id="turbidityPercent">
                            <i class="fas fa-percentage mr-1"></i>--%
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Good</span>
                            <span class="text-green-600 dark:text-green-400 font-medium">≤ 2 NTU</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Medium</span>
                            <span class="text-yellow-600 dark:text-yellow-400 font-medium">2-5 NTU</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Critical</span>
                            <span class="text-red-600 dark:text-red-400 font-medium">> 5 NTU</span>
                        </div>
                    </div>
                </div>

                <!-- TDS Sensor -->
                <div class="sensor-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <div class="w-16 h-16 rounded-2xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mr-4">
                                <i class="fas fa-flask text-emerald-500 dark:text-emerald-400 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white">TDS</h3>
                                <p class="text-gray-500 dark:text-gray-400">Dissolved Solids</p>
                            </div>
                        </div>
                        <div class="quality-badge" id="tdsQuality">--</div>
                    </div>
                    <div class="text-center mb-4">
                        <div class="value-display text-4xl text-gray-900 dark:text-white mb-2" id="tdsValue">--</div>
                        <div class="text-lg text-gray-500 dark:text-gray-400">%</div>
                        <div class="text-xs text-gray-400 dark:text-gray-500 mt-1" id="tdsRaw">Raw: -- ppm</div>
                    </div>
                    <div class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Good</span>
                            <span class="text-green-600 dark:text-green-400 font-medium">≤ 30%</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Medium</span>
                            <span class="text-yellow-600 dark:text-yellow-400 font-medium">30-60%</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Critical</span>
                            <span class="text-red-600 dark:text-red-400 font-medium">> 60%</span>
                        </div>
                    </div>
                </div>

                <!-- pH Sensor -->
                <div class="sensor-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <div class="w-16 h-16 rounded-2xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center mr-4">
                                <i class="fas fa-vial text-purple-500 dark:text-purple-400 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white">pH Level</h3>
                                <p class="text-gray-500 dark:text-gray-400">Acidity/Alkalinity</p>
                            </div>
                        </div>
                        <div class="quality-badge" id="phQuality">--</div>
                    </div>
                    <div class="text-center mb-4">
                        <div class="value-display text-4xl text-gray-900 dark:text-white mb-2" id="phValue">--</div>
                        <div class="text-lg text-gray-500 dark:text-gray-400">pH</div>
                    </div>
                    <div class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Good</span>
                            <span class="text-green-600 dark:text-green-400 font-medium">6.0-8.0</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Medium</span>
                            <span class="text-yellow-600 dark:text-yellow-400 font-medium">4.0-6.0 & 8.0-10.0</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Critical</span>
                            <span class="text-red-600 dark:text-red-400 font-medium">< 4.0 or > 10.0</span>
                        </div>
                    </div>
                </div>

                <!-- Temperature Sensor -->
                <div class="sensor-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <div class="w-16 h-16 rounded-2xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center mr-4">
                                <i class="fas fa-thermometer-half text-red-500 dark:text-red-400 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Temperature</h3>
                                <p class="text-gray-500 dark:text-gray-400">Water Temp</p>
                            </div>
                        </div>
                        <div class="quality-badge" id="temperatureQuality">--</div>
                    </div>
                    <div class="text-center mb-4">
                        <div class="value-display text-4xl text-gray-900 dark:text-white mb-2" id="temperatureValue">--</div>
                        <div class="text-lg text-gray-500 dark:text-gray-400">°C</div>
                    </div>
                    <div class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Good</span>
                            <span class="text-green-600 dark:text-green-400 font-medium">15-25°C</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Medium</span>
                            <span class="text-yellow-600 dark:text-yellow-400 font-medium">10-30°C</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Critical</span>
                            <span class="text-red-600 dark:text-red-400 font-medium">< 10°C or > 30°C</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Water Quality Status -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                            <i class="fas fa-shield-alt text-blue-500 mr-3"></i>
                            Overall Water Quality Status
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400">Comprehensive assessment of all water quality parameters</p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Overall Status</div>
                        <div class="text-2xl font-bold" id="overallStatus">--</div>
                    </div>
                </div>
                
                <div id="waterQualityAlerts" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Alerts will be dynamically inserted here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleTimeString();
        }

        // Conversion functions
        function convertTurbidityToPercentage(rawValue) {
            // Formula: (Raw Value - 1) / 2999 * 100
            return Math.max(0, Math.min(100, ((rawValue - 1) / 2999) * 100));
        }

        function convertTDSToPercentage(ppmValue) {
            // Convert TDS ppm to percentage (assuming max reasonable TDS is ~1000 ppm)
            return Math.max(0, Math.min(100, (ppmValue / 1000) * 100));
        }

        function getTurbidityStatus(ntuValue) {
            if (ntuValue <= 2) return { status: 'Good', color: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' };
            if (ntuValue <= 5) return { status: 'Medium', color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' };
            return { status: 'Critical', color: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' };
        }

        function getQualityStatus(value, thresholds) {
            if (value <= thresholds.good) return { status: 'Good', color: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' };
            if (value <= thresholds.warning) return { status: 'Medium', color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' };
            return { status: 'Critical', color: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' };
        }

        function getPHStatus(value) {
            // New pH standards
            if (value >= 6 && value <= 8) return { status: 'Good', color: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' };
            if ((value >= 4 && value < 6) || (value > 8 && value <= 10)) return { status: 'Medium', color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' };
            return { status: 'Critical', color: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' };
        }

        function getTemperatureStatus(value) {
            // Realistic water temperature standards
            if (value >= 15 && value <= 25) return { status: 'Good', color: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' };
            if (value >= 10 && value <= 30) return { status: 'Medium', color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' };
            return { status: 'Critical', color: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' };
        }

        function updateData() {
            fetch('../../api/get_readings.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);

                    const latest = data.latest;
                    if (latest) {
                        // Convert raw values to percentages
                        const turbidityPercent = convertTurbidityToPercentage(parseFloat(latest.turbidity_ntu));
                        const tdsPercent = convertTDSToPercentage(parseFloat(latest.tds_ppm));
                        
                        // Update sensor values
                        document.getElementById('turbidityValue').textContent = parseFloat(latest.turbidity_ntu).toFixed(0);
                        document.getElementById('tdsValue').textContent = tdsPercent.toFixed(1);
                        document.getElementById('phValue').textContent = parseFloat(latest.ph).toFixed(1);
                        document.getElementById('temperatureValue').textContent = parseFloat(latest.temperature).toFixed(1);
                        
                        // Update percentage display for turbidity
                        document.getElementById('turbidityPercent').innerHTML = `<i class="fas fa-percentage mr-1"></i>${turbidityPercent.toFixed(1)}%`;
                        document.getElementById('tdsRaw').textContent = `Raw: ${parseFloat(latest.tds_ppm).toFixed(0)} ppm`;

                        // Update quality badges with new thresholds
                        const turbidityStatus = getTurbidityStatus(parseFloat(latest.turbidity_ntu));
                        const tdsStatus = getQualityStatus(tdsPercent, { good: 30, warning: 60 });
                        const phStatus = getPHStatus(parseFloat(latest.ph));
                        const tempStatus = getTemperatureStatus(parseFloat(latest.temperature));

                        document.getElementById('turbidityQuality').textContent = turbidityStatus.status;
                        document.getElementById('turbidityQuality').className = `quality-badge ${turbidityStatus.color}`;

                        document.getElementById('tdsQuality').textContent = tdsStatus.status;
                        document.getElementById('tdsQuality').className = `quality-badge ${tdsStatus.color}`;

                        document.getElementById('phQuality').textContent = phStatus.status;
                        document.getElementById('phQuality').className = `quality-badge ${phStatus.color}`;

                        document.getElementById('temperatureQuality').textContent = tempStatus.status;
                        document.getElementById('temperatureQuality').className = `quality-badge ${tempStatus.color}`;

                        // Update last update time
                        document.getElementById('lastUpdate').textContent = formatDate(latest.reading_time);

                        // Update water quality alerts with raw values for proper threshold evaluation
                        updateWaterQualityAlerts(
                            parseFloat(latest.turbidity_ntu),
                            parseFloat(latest.tds_ppm),
                            parseFloat(latest.ph),
                            parseFloat(latest.temperature)
                        );
                    }
                })
                .catch(error => {
                    console.error('Error fetching data:', error);
                    document.getElementById('turbidityValue').textContent = 'Error';
                    document.getElementById('tdsValue').textContent = 'Error';
                    document.getElementById('phValue').textContent = 'Error';
                    document.getElementById('temperatureValue').textContent = 'Error';
                });
        }

        function updateWaterQualityAlerts(turbidity, tds, ph, temperature) {
            const alertsContainer = document.getElementById('waterQualityAlerts');
            const alerts = [];
            let overallScore = 0;
            let totalParameters = 4;

            // Convert to percentage for display
            const turbidityPercent = convertTurbidityToPercentage(turbidity);
            const tdsPercent = convertTDSToPercentage(tds);
            
            // Evaluate each parameter with new thresholds
            if (turbidity <= 2) { alerts.push({ type: 'success', title: 'Turbidity', message: `Good water clarity (${turbidity.toFixed(0)} NTU, ${turbidityPercent.toFixed(1)}%)`, icon: 'fa-check-circle' }); overallScore++; }
            else if (turbidity <= 5) { alerts.push({ type: 'warning', title: 'Turbidity', message: `Medium clarity - monitor closely (${turbidity.toFixed(0)} NTU, ${turbidityPercent.toFixed(1)}%)`, icon: 'fa-exclamation-triangle' }); overallScore += 0.5; }
            else { alerts.push({ type: 'danger', title: 'Turbidity', message: `Critical clarity - requires attention (${turbidity.toFixed(0)} NTU, ${turbidityPercent.toFixed(1)}%)`, icon: 'fa-exclamation-circle' }); }

            if (tdsPercent <= 30) { alerts.push({ type: 'success', title: 'TDS', message: `Good dissolved solids content (${tds.toFixed(0)} ppm, ${tdsPercent.toFixed(1)}%)`, icon: 'fa-check-circle' }); overallScore++; }
            else if (tdsPercent <= 60) { alerts.push({ type: 'warning', title: 'TDS', message: `Medium dissolved solids (${tds.toFixed(0)} ppm, ${tdsPercent.toFixed(1)}%)`, icon: 'fa-exclamation-triangle' }); overallScore += 0.5; }
            else { alerts.push({ type: 'danger', title: 'TDS', message: `High dissolved solids - treatment needed (${tds.toFixed(0)} ppm, ${tdsPercent.toFixed(1)}%)`, icon: 'fa-exclamation-circle' }); }

            if (ph >= 6 && ph <= 8) { alerts.push({ type: 'success', title: 'pH Level', message: 'Good pH range', icon: 'fa-check-circle' }); overallScore++; }
            else if ((ph >= 4 && ph < 6) || (ph > 8 && ph <= 10)) { alerts.push({ type: 'warning', title: 'pH Level', message: 'Medium pH - monitor and adjust as needed', icon: 'fa-exclamation-triangle' }); overallScore += 0.5; }
            else { alerts.push({ type: 'danger', title: 'pH Level', message: 'Critical pH - immediate adjustment needed', icon: 'fa-exclamation-circle' }); }

            if (temperature >= 15 && temperature <= 25) { alerts.push({ type: 'success', title: 'Temperature', message: 'Good water temperature', icon: 'fa-check-circle' }); overallScore++; }
            else if (temperature >= 10 && temperature <= 30) { alerts.push({ type: 'warning', title: 'Temperature', message: 'Medium temperature - monitor closely', icon: 'fa-exclamation-triangle' }); overallScore += 0.5; }
            else { alerts.push({ type: 'danger', title: 'Temperature', message: 'Critical temperature - immediate attention needed', icon: 'fa-exclamation-circle' }); }

            // Determine overall status
            const overallPercentage = (overallScore / totalParameters) * 100;
            let overallStatus, overallColor;
            if (overallPercentage >= 75) {
                overallStatus = 'Excellent';
                overallColor = 'text-green-600 dark:text-green-400';
            } else if (overallPercentage >= 50) {
                overallStatus = 'Good';
                overallColor = 'text-yellow-600 dark:text-yellow-400';
            } else {
                overallStatus = 'Poor';
                overallColor = 'text-red-600 dark:text-red-400';
            }

            document.getElementById('overallStatus').textContent = overallStatus;
            document.getElementById('overallStatus').className = `text-2xl font-bold ${overallColor}`;

            // Generate alert cards
            alertsContainer.innerHTML = alerts.map(alert => `
                <div class="flex items-start p-6 rounded-xl border-l-4 ${
                    alert.type === 'success' ? 'bg-green-50 dark:bg-green-900/20 border-green-500' :
                    alert.type === 'warning' ? 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-500' :
                    'bg-red-50 dark:bg-red-900/20 border-red-500'
                }">
                    <div class="flex-shrink-0 mr-4">
                        <i class="fas ${alert.icon} text-xl ${
                            alert.type === 'success' ? 'text-green-500' :
                            alert.type === 'warning' ? 'text-yellow-500' :
                            'text-red-500'
                        }"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">${alert.title}</h3>
                        <p class="text-gray-600 dark:text-gray-400">${alert.message}</p>
                    </div>
                </div>
            `).join('');
        }

        // Update data every 5 seconds
        updateData();
        setInterval(updateData, 5000);

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
    </script>
</body>
</html>
