<?php
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login/index.php');
    exit;
}

require_once '../../config/database.php';

// Get latest readings for system status
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $result = $conn->query("SELECT * FROM water_readings ORDER BY reading_time DESC LIMIT 1");
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
    <title>Control Panel - Water Quality System</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
        .control-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .control-card::before {
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
        .control-card:hover::before {
            transform: scaleX(1);
        }
        .control-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .dark .control-card:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #F8FAFC 0%, #E2E8F0 100%);
        }
        .dark .gradient-bg {
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
        }
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #3B82F6;
        }
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        .slider.disabled {
            background-color: #6B7280;
            cursor: not-allowed;
        }
        .automation-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .dark .automation-card {
            background: linear-gradient(135deg, #4C1D95 0%, #7C3AED 100%);
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
                        <i class="fas fa-sliders-h text-blue-500 mr-3"></i>
                        Control Panel
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 text-lg">Manage water quality system automation and controls</p>
                </div>
                <div class="flex items-center space-x-6">
                    <div class="text-center">
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">System Status</div>
                        <div class="flex items-center space-x-2">
                            <div class="status-indicator bg-green-500"></div>
                            <span class="text-lg font-semibold text-green-600 dark:text-green-400">Online</span>
                        </div>
                    </div>
                    <button id="themeToggle" class="p-3 rounded-xl bg-white dark:bg-gray-800 shadow-lg hover:shadow-xl transition-all duration-200">
                        <i class="fas fa-sun text-yellow-500 dark:hidden text-lg"></i>
                        <i class="fas fa-moon text-blue-300 hidden dark:block text-lg"></i>
                    </button>
                </div>
            </div>

            <!-- System Status Overview -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="control-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Active Relays</h3>
                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400" id="activeRelays">0</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                            <i class="fas fa-plug text-blue-500 dark:text-blue-400 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="control-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">System Uptime</h3>
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400" id="uptime">--:--:--</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                            <i class="fas fa-clock text-green-500 dark:text-green-400 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="control-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Last Command</h3>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400" id="lastCommand">None</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                            <i class="fas fa-terminal text-purple-500 dark:text-purple-400 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="control-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Auto Mode</h3>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400" id="autoMode">Disabled</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                            <i class="fas fa-robot text-orange-500 dark:text-orange-400 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Relay Control Panel -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8 mb-8">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                            <i class="fas fa-plug text-blue-500 mr-3"></i>
                            Relay Control System
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400">Direct control over water quality system components</p>
                    </div>
                    <div class="flex space-x-3">
                        <button id="allOn" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors">
                            <i class="fas fa-power-off mr-2"></i>All On
                        </button>
                        <button id="allOff" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors">
                            <i class="fas fa-stop mr-2"></i>All Off
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Pool to Filter Pump -->
                    <div class="control-card bg-gray-50 dark:bg-gray-700 rounded-xl p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mr-3">
                                    <i class="fas fa-pump text-blue-500 dark:text-blue-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Pool to Filter</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">IN1 - Main Pump</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Status:</span>
                            <span class="text-sm font-medium" id="relay1Status">Offline</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <label class="switch">
                                <input type="checkbox" data-relay="1" onchange="toggleRelay(this)">
                                <span class="slider"></span>
                            </label>
                            <div class="text-right">
                                <div class="text-xs text-gray-500 dark:text-gray-400">Power</div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-white" id="relay1Power">0W</div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter to Pool Pump -->
                    <div class="control-card bg-gray-50 dark:bg-gray-700 rounded-xl p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mr-3">
                                    <i class="fas fa-water text-emerald-500 dark:text-emerald-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Filter to Pool</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">IN2 - Return Pump</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Status:</span>
                            <span class="text-sm font-medium" id="relay2Status">Offline</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <label class="switch">
                                <input type="checkbox" data-relay="2" onchange="toggleRelay(this)">
                                <span class="slider"></span>
                            </label>
                            <div class="text-right">
                                <div class="text-xs text-gray-500 dark:text-gray-400">Power</div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-white" id="relay2Power">0W</div>
                            </div>
                        </div>
                    </div>

                    <!-- Dispenser -->
                    <div class="control-card bg-gray-50 dark:bg-gray-700 rounded-xl p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center mr-3">
                                    <i class="fas fa-tint text-purple-500 dark:text-purple-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Dispenser</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">IN3 - Chemical Dispenser</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Status:</span>
                            <span class="text-sm font-medium" id="relay3Status">Offline</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <label class="switch">
                                <input type="checkbox" data-relay="3" onchange="toggleRelay(this)">
                                <span class="slider"></span>
                            </label>
                            <div class="text-right">
                                <div class="text-xs text-gray-500 dark:text-gray-400">Power</div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-white" id="relay3Power">0W</div>
                            </div>
                        </div>
                    </div>

                    <!-- Spare Relay -->
                    <div class="control-card bg-gray-50 dark:bg-gray-700 rounded-xl p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-lg bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center mr-3">
                                    <i class="fas fa-plug text-orange-500 dark:text-orange-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Spare</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">IN4 - Backup Relay</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Status:</span>
                            <span class="text-sm font-medium" id="relay4Status">Offline</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <label class="switch">
                                <input type="checkbox" data-relay="4" onchange="toggleRelay(this)">
                                <span class="slider"></span>
                            </label>
                            <div class="text-right">
                                <div class="text-xs text-gray-500 dark:text-gray-400">Power</div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-white" id="relay4Power">0W</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Automation & Scheduling -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Automation Rules -->
                <div class="control-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                                <i class="fas fa-robot text-purple-500 mr-3"></i>
                                Automation Rules
                            </h2>
                            <p class="text-gray-600 dark:text-gray-400">Smart control based on water quality parameters</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="autoModeToggle" onchange="toggleAutoMode(this)">
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <h4 class="font-semibold text-gray-900 dark:text-white">pH Correction</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Auto-adjust pH when out of range</p>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-green-600 dark:text-green-400">Active</div>
                                <div class="text-xs text-gray-500">pH < 6.5 or > 8.5</div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <h4 class="font-semibold text-gray-900 dark:text-white">Filtration Cycle</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Automatic filter operation</p>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-green-600 dark:text-green-400">Active</div>
                                <div class="text-xs text-gray-500">Every 6 hours</div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div>
                                <h4 class="font-semibold text-gray-900 dark:text-white">Emergency Shutdown</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Stop all systems if critical</p>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-red-600 dark:text-red-400">Standby</div>
                                <div class="text-xs text-gray-500">pH < 5.0 or > 10.0</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Logs -->
                <div class="control-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                                <i class="fas fa-list-alt text-blue-500 mr-3"></i>
                                System Logs
                            </h2>
                            <p class="text-gray-600 dark:text-gray-400">Recent control actions and system events</p>
                        </div>
                        <button class="px-3 py-1 text-sm bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300 rounded-lg hover:bg-blue-200 dark:hover:bg-blue-800">
                            <i class="fas fa-sync-alt mr-1"></i>Refresh
                        </button>
                    </div>

                    <div class="space-y-3 max-h-64 overflow-y-auto">
                        <div class="flex items-start space-x-3 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                            <i class="fas fa-check-circle text-green-500 mt-1"></i>
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Relay 1 activated</div>
                                <div class="text-xs text-gray-500">2 minutes ago</div>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3 p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                            <i class="fas fa-exclamation-triangle text-yellow-500 mt-1"></i>
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">pH level high - dispenser activated</div>
                                <div class="text-xs text-gray-500">15 minutes ago</div>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <i class="fas fa-info-circle text-blue-500 mt-1"></i>
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Automation mode enabled</div>
                                <div class="text-xs text-gray-500">1 hour ago</div>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <i class="fas fa-clock text-gray-500 mt-1"></i>
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Scheduled maintenance completed</div>
                                <div class="text-xs text-gray-500">2 hours ago</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let startTime = Date.now();
        let activeRelays = 0;

        function formatUptime() {
            const now = Date.now();
            const diff = now - startTime;
            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);
            return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }

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
                        updateRelayStates(data.states);
                    }
                    // Update last command
                    document.getElementById('lastCommand').textContent = `Relay ${relay} ${state === 1 ? 'ON' : 'OFF'}`;
                } else {
                    console.error('Error updating relay state:', data.error);
                    checkbox.checked = !checkbox.checked; // Revert the toggle
                }
            })
            .catch(error => {
                console.error('Error:', error);
                checkbox.checked = !checkbox.checked; // Revert the toggle
            })
            .finally(() => {
                checkbox.disabled = false; // Re-enable the checkbox
            });
        }

        function updateRelayStates(states) {
            activeRelays = 0;
            states.forEach(state => {
                const checkbox = document.querySelector(`input[data-relay="${state.relay_number}"]`);
                const statusElement = document.getElementById(`relay${state.relay_number}Status`);
                const powerElement = document.getElementById(`relay${state.relay_number}Power`);
                
                if (checkbox) {
                    checkbox.checked = state.state === 1;
                }
                
                if (statusElement) {
                    statusElement.textContent = state.state === 1 ? 'Online' : 'Offline';
                    statusElement.className = `text-sm font-medium ${state.state === 1 ? 'text-green-600 dark:text-green-400' : 'text-gray-600 dark:text-gray-400'}`;
                }
                
                if (powerElement) {
                    const power = state.state === 1 ? Math.floor(Math.random() * 500) + 100 : 0;
                    powerElement.textContent = `${power}W`;
                }
                
                if (state.state === 1) activeRelays++;
            });
            
            document.getElementById('activeRelays').textContent = activeRelays;
        }

        function fetchRelayStates() {
            fetch('../../api/relay_control.php')
                .then(response => response.json())
                .then(data => {
                    if (data.states) {
                        updateRelayStates(data.states);
                    }
                })
                .catch(error => console.error('Error fetching relay states:', error));
        }

        function toggleAutoMode(checkbox) {
            const isEnabled = checkbox.checked;
            document.getElementById('autoMode').textContent = isEnabled ? 'Enabled' : 'Disabled';
            document.getElementById('autoMode').className = `text-sm font-medium ${isEnabled ? 'text-green-600 dark:text-green-400' : 'text-gray-600 dark:text-gray-400'}`;
            
            // Add to logs
            const logEntry = document.createElement('div');
            logEntry.className = `flex items-start space-x-3 p-3 ${isEnabled ? 'bg-green-50 dark:bg-green-900/20' : 'bg-gray-50 dark:bg-gray-700'} rounded-lg`;
            logEntry.innerHTML = `
                <i class="fas ${isEnabled ? 'fa-check-circle text-green-500' : 'fa-times-circle text-gray-500'} mt-1"></i>
                <div class="flex-1">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">Automation mode ${isEnabled ? 'enabled' : 'disabled'}</div>
                    <div class="text-xs text-gray-500">Just now</div>
                </div>
            `;
            
            const logsContainer = document.querySelector('.space-y-3');
            logsContainer.insertBefore(logEntry, logsContainer.firstChild);
            
            // Remove oldest log if more than 4 entries
            if (logsContainer.children.length > 4) {
                logsContainer.removeChild(logsContainer.lastChild);
            }
        }

        // All On/Off buttons
        document.getElementById('allOn').addEventListener('click', () => {
            const checkboxes = document.querySelectorAll('input[data-relay]');
            checkboxes.forEach(checkbox => {
                if (!checkbox.checked) {
                    checkbox.checked = true;
                    toggleRelay(checkbox);
                }
            });
        });

        document.getElementById('allOff').addEventListener('click', () => {
            const checkboxes = document.querySelectorAll('input[data-relay]');
            checkboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    checkbox.checked = false;
                    toggleRelay(checkbox);
                }
            });
        });

        // Update uptime every second
        setInterval(() => {
            document.getElementById('uptime').textContent = formatUptime();
        }, 1000);

        // Fetch relay states every 5 seconds
        fetchRelayStates();
        setInterval(fetchRelayStates, 5000);

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
