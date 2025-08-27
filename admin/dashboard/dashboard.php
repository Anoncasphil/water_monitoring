<?php
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login/index.php');
    exit;
}

require_once '../../config/database.php';

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

    // Get second-to-last readings (skip the latest one)
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        $result = $conn->query("SELECT * FROM water_readings ORDER BY reading_time DESC LIMIT 1 OFFSET 1");
        $readings = $result->fetch_all(MYSQLI_ASSOC);
        
        // Get data for charts (excluding the latest reading)
        $chartResult = $conn->query("SELECT reading_time, turbidity, tds FROM water_readings ORDER BY reading_time DESC LIMIT 1 OFFSET 1, 24");
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
                        <div class="text-sm text-gray-500 dark:text-gray-400">NTU</div>
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
                        <div class="text-sm text-gray-500 dark:text-gray-400">ppm</div>
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
                </div>
                <div id="waterQualityAlerts" class="space-y-4">
                    <!-- Alerts will be dynamically inserted here -->
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
                                    <i class="fas fa-filter mr-1"></i>Turb
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <i class="fas fa-flask mr-1"></i>TDS
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
            fetch('../../api/relay_control.php')
                .then(response => response.json())
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

                    // Update sensor cards
                    const latest = data.latest;
                    if (latest) {
                        document.getElementById('turbidityValue').textContent = parseFloat(latest.turbidity_ntu).toFixed(1);
                        document.getElementById('tdsValue').textContent = parseFloat(latest.tds_ppm).toFixed(1);
                        document.getElementById('phValue').textContent = parseFloat(latest.ph).toFixed(2);
                        document.getElementById('temperatureValue').textContent = parseFloat(latest.temperature).toFixed(2);
                        document.getElementById('turbidityTime').textContent = `Last updated: ${formatDate(latest.reading_time)}`;
                        document.getElementById('tdsTime').textContent = `Last updated: ${formatDate(latest.reading_time)}`;
                        document.getElementById('phTime').textContent = `Last updated: ${formatDate(latest.reading_time)}`;
                        document.getElementById('temperatureTime').textContent = `Last updated: ${formatDate(latest.reading_time)}`;
                    }

                    // Update table
                    if (data.recent && data.recent.length > 0) {
                        const tableHtml = data.recent.map(reading => `
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-300">${formatDate(reading.reading_time)}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-300">${parseFloat(reading.turbidity_ntu).toFixed(1)}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-300">${parseFloat(reading.tds_ppm).toFixed(1)}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-300">${parseFloat(reading.ph).toFixed(2)}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-300">${parseFloat(reading.temperature).toFixed(2)}</td>
                            </tr>
                        `).join('');
                        document.getElementById('readingsTable').innerHTML = tableHtml;
                    }

                    // Update chart
                    if (data.historical && data.historical.length > 0) {
                        updateChart(data.historical);
                    }

                    // Update water quality alerts
                    updateWaterQualityAlerts(
                        parseFloat(document.getElementById('turbidityValue').textContent),
                        parseFloat(document.getElementById('tdsValue').textContent),
                        parseFloat(document.getElementById('phValue').textContent),
                        parseFloat(document.getElementById('temperatureValue').textContent)
                    );
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
                        label: 'TDS (ppm)',
                        data: data.map(d => parseFloat(d.tds_ppm)),
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
        setTimeout(() => {
            updateData();
            fetchRelayStates();
        }, 500); // Initial 500ms delay
        
        setInterval(() => {
            updateData();
            fetchRelayStates();
        }, 1000); // Update every 1 second instead of 5 seconds

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

        function evaluateWaterQuality(turbidity, tds, ph, temperature) {
            const alerts = [];
            
            // Evaluate Turbidity
            if (turbidity >= thresholds.turbidity.danger) {
                alerts.push({
                    type: 'danger',
                    message: `High turbidity (${turbidity.toFixed(1)} NTU) - Water is very cloudy and may contain harmful particles`
                });
            } else if (turbidity >= thresholds.turbidity.warning) {
                alerts.push({
                    type: 'warning',
                    message: `Elevated turbidity (${turbidity.toFixed(1)} NTU) - Water clarity is reduced`
                });
            } else if (turbidity <= thresholds.turbidity.good) {
                alerts.push({
                    type: 'success',
                    message: `Good turbidity (${turbidity.toFixed(1)} NTU) - Water is clear`
                });
            }

            // Evaluate TDS
            if (tds >= thresholds.tds.danger) {
                alerts.push({
                    type: 'danger',
                    message: `High TDS (${tds.toFixed(0)} ppm) - Water contains excessive dissolved solids`
                });
            } else if (tds >= thresholds.tds.warning) {
                alerts.push({
                    type: 'warning',
                    message: `Elevated TDS (${tds.toFixed(0)} ppm) - Water may need treatment`
                });
            } else if (tds <= thresholds.tds.good) {
                alerts.push({
                    type: 'success',
                    message: `Good TDS (${tds.toFixed(0)} ppm) - Water is within acceptable range`
                });
            }

            // Evaluate pH
            if (ph < thresholds.ph.danger.min || ph > thresholds.ph.danger.max) {
                alerts.push({
                    type: 'danger',
                    message: `Extreme pH (${ph.toFixed(1)}) - Water is too acidic or alkaline`
                });
            } else if (ph < thresholds.ph.warning.min || ph > thresholds.ph.warning.max) {
                alerts.push({
                    type: 'warning',
                    message: `Unbalanced pH (${ph.toFixed(1)}) - Water may need pH adjustment`
                });
            } else if (ph >= thresholds.ph.good.min && ph <= thresholds.ph.good.max) {
                alerts.push({
                    type: 'success',
                    message: `Good pH (${ph.toFixed(1)}) - Water is within ideal range`
                });
            }

            // Evaluate Temperature
            if (temperature < thresholds.temperature.danger.min || temperature > thresholds.temperature.danger.max) {
                alerts.push({
                    type: 'danger',
                    message: `Extreme temperature (${temperature.toFixed(1)}°C) - Water is too hot or cold`
                });
            } else if (temperature < thresholds.temperature.warning.min || temperature > thresholds.temperature.warning.max) {
                alerts.push({
                    type: 'warning',
                    message: `Unusual temperature (${temperature.toFixed(1)}°C) - Monitor water temperature`
                });
            } else if (temperature >= thresholds.temperature.good.min && temperature <= thresholds.temperature.good.max) {
                alerts.push({
                    type: 'success',
                    message: `Good temperature (${temperature.toFixed(1)}°C) - Water is at ideal temperature`
                });
            }

            return alerts;
        }

        function updateWaterQualityAlerts(turbidity, tds, ph, temperature) {
            const alertsContainer = document.getElementById('waterQualityAlerts');
            const alerts = evaluateWaterQuality(turbidity, tds, ph, temperature);
            
            alertsContainer.innerHTML = alerts.map(alert => `
                <div class="flex items-center p-4 rounded-lg ${
                    alert.type === 'danger' ? 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300' :
                    alert.type === 'warning' ? 'bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-300' :
                    'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300'
                }">
                    <i class="fas ${
                        alert.type === 'danger' ? 'fa-exclamation-circle' :
                        alert.type === 'warning' ? 'fa-exclamation-triangle' :
                        'fa-check-circle'
                    } mr-3"></i>
                    <span>${alert.message}</span>
                </div>
            `).join('');
        }
    </script>
        </div>
    </div>
</body>
</html> 