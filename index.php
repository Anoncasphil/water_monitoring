<?php
require_once 'config/database.php';

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

// Get latest readings
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $result = $conn->query("SELECT * FROM water_readings ORDER BY reading_time DESC LIMIT 10");
    $readings = $result->fetch_all(MYSQLI_ASSOC);
    
    // Get data for charts
    $chartResult = $conn->query("SELECT reading_time, turbidity, tds FROM water_readings ORDER BY reading_time DESC LIMIT 24");
    $chartData = $chartResult->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $readings = [];
    $chartData = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Quality Monitor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-center text-gray-800 mb-8">Water Quality Monitor</h1>
        
        <!-- Sensor Cards Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Turbidity Card -->
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg transform transition-all duration-300 hover:-translate-y-1 hover:shadow-xl">
                <div class="p-6 text-center text-white">
                    <h3 class="text-2xl font-semibold mb-4">Turbidity</h3>
                    <div class="text-5xl font-bold mb-2" id="turbidityValue">--</div>
                    <div class="text-xl text-blue-100">NTU</div>
                    <div class="text-sm text-blue-100 mt-4" id="turbidityTime">Last updated: --</div>
                </div>
            </div>

            <!-- TDS Card -->
            <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl shadow-lg transform transition-all duration-300 hover:-translate-y-1 hover:shadow-xl">
                <div class="p-6 text-center text-white">
                    <h3 class="text-2xl font-semibold mb-4">TDS</h3>
                    <div class="text-5xl font-bold mb-2" id="tdsValue">--</div>
                    <div class="text-xl text-emerald-100">ppm</div>
                    <div class="text-sm text-emerald-100 mt-4" id="tdsTime">Last updated: --</div>
                </div>
            </div>
        </div>

        <!-- Chart and Table Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Chart -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-lg p-6">
                <h5 class="text-xl font-semibold text-gray-800 mb-6">Historical Data</h5>
                <div class="h-[400px]">
                    <canvas id="readingsChart"></canvas>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h5 class="text-xl font-semibold text-gray-800 mb-6">Recent Readings</h5>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turb</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">TDS</th>
                            </tr>
                        </thead>
                        <tbody id="readingsTable" class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-sm text-gray-500">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
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

        function updateData() {
            fetch('get_readings.php')
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
                        document.getElementById('turbidityTime').textContent = `Last updated: ${formatDate(latest.reading_time)}`;
                        document.getElementById('tdsTime').textContent = `Last updated: ${formatDate(latest.reading_time)}`;
                    }

                    // Update table
                    if (data.recent && data.recent.length > 0) {
                        const tableHtml = data.recent.map(reading => `
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-900">${formatDate(reading.reading_time)}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">${parseFloat(reading.turbidity_ntu).toFixed(1)}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">${parseFloat(reading.tds_ppm).toFixed(1)}</td>
                            </tr>
                        `).join('');
                        document.getElementById('readingsTable').innerHTML = tableHtml;
                    }

                    // Update chart
                    if (data.historical && data.historical.length > 0) {
                        updateChart(data.historical);
                    }
                })
                .catch(error => {
                    console.error('Error fetching data:', error);
                    document.getElementById('turbidityValue').textContent = 'Error';
                    document.getElementById('tdsValue').textContent = 'Error';
                    document.getElementById('turbidityTime').textContent = 'Failed to update';
                    document.getElementById('tdsTime').textContent = 'Failed to update';
                    document.getElementById('readingsTable').innerHTML = '<tr><td colspan="3" class="px-4 py-3 text-sm text-red-500">Error loading data</td></tr>';
                });
        }

        function updateChart(data) {
            const ctx = document.getElementById('readingsChart').getContext('2d');
            
            if (readingsChart instanceof Chart) {
                readingsChart.destroy();
            }

            readingsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => formatDate(d.reading_time)),
                    datasets: [{
                        label: 'Turbidity (NTU)',
                        data: data.map(d => parseFloat(d.turbidity_ntu)),
                        borderColor: 'rgb(59, 130, 246)',  // Tailwind blue-500
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.1,
                        fill: true
                    }, {
                        label: 'TDS (ppm)',
                        data: data.map(d => parseFloat(d.tds_ppm)),
                        borderColor: 'rgb(16, 185, 129)',  // Tailwind emerald-500
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                padding: 20,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        }

        // Update data every 5 seconds
        updateData();
        setInterval(updateData, 5000);
    </script>
</body>
</html> 