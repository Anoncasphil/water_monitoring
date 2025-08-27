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
        .control-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
        
        /* Toast notification styles */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            transform: translateX(100%);
            transition: transform 0.3s ease;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast.success {
            background: #10B981;
            color: white;
            border-left: 4px solid #059669;
        }
        
        .toast.error {
            background: #EF4444;
            color: white;
            border-left: 4px solid #DC2626;
        }
        
        .toast.warning {
            background: #F59E0B;
            color: white;
            border-left: 4px solid #D97706;
        }
        
        .toast.info {
            background: #3B82F6;
            color: white;
            border-left: 4px solid #2563EB;
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
        .relay-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .relay-card.active {
            border-color: #10B981;
            background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%);
        }
        .dark .relay-card.active {
            background: linear-gradient(135deg, #064E3B 0%, #065F46 100%);
            border-color: #10B981;
        }
        .relay-card.inactive {
            border-color: #E5E7EB;
            background: linear-gradient(135deg, #F9FAFB 0%, #F3F4F6 100%);
        }
        .dark .relay-card.inactive {
            background: linear-gradient(135deg, #374151 0%, #4B5563 100%);
            border-color: #6B7280;
        }
        .power-button {
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        .power-button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.3s ease, height 0.3s ease;
        }
        .power-button:active::before {
            width: 200px;
            height: 200px;
        }
        .status-badge {
            transition: all 0.3s ease;
        }
        .status-badge.online {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
        }
        .status-badge.offline {
            background: linear-gradient(135deg, #6B7280 0%, #4B5563 100%);
            color: white;
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
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
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
            </div>

            <!-- Automation Control Panel -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8 mb-8">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                            <i class="fas fa-robot text-purple-500 mr-3"></i>
                            Automation System
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400">Smart automation based on water quality sensor readings</p>
                    </div>
                    <div class="flex space-x-3">
                        <button id="checkAutomation" class="power-button px-6 py-3 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white rounded-xl font-semibold transition-all duration-200 shadow-lg hover:shadow-xl">
                            <i class="fas fa-sync-alt mr-2"></i>Check Now
                        </button>
                    </div>
                </div>

                <!-- Automation Status -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="control-card bg-gradient-to-r from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 rounded-2xl p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Automation Status</h3>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400" id="automationStatus">Loading...</p>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                                <i class="fas fa-robot text-purple-500 dark:text-purple-400 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="control-card bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-2xl p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">TDS Status</h3>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400" id="tdsStatus">Loading...</p>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                <i class="fas fa-tint text-blue-500 dark:text-blue-400 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="control-card bg-gradient-to-r from-emerald-50 to-emerald-100 dark:from-emerald-900/20 dark:to-emerald-800/20 rounded-2xl p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Turbidity Status</h3>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400" id="turbidityStatus">Loading...</p>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                                <i class="fas fa-water text-emerald-500 dark:text-emerald-400 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Automation Controls -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="control-card bg-white dark:bg-gray-700 rounded-2xl p-6">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">
                            <i class="fas fa-cog text-gray-500 mr-2"></i>
                            Automation Settings
                        </h3>
                        
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Master Automation</label>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Enable/disable all automation</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="masterAutomation" onchange="updateAutomationSettings()">
                                    <span class="slider"></span>
                                </label>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Filter Automation</label>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Auto-activate filter based on water quality</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="filterAutomation" onchange="updateAutomationSettings()">
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="control-card bg-white dark:bg-gray-700 rounded-2xl p-6">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">
                            <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                            Current Analysis
                        </h3>
                        
                        <div class="space-y-3">
                            <div class="text-sm">
                                <span class="font-medium text-gray-700 dark:text-gray-300">Latest Reading:</span>
                                <span class="text-gray-600 dark:text-gray-400" id="latestReading">Loading...</span>
                            </div>
                            <div class="text-sm">
                                <span class="font-medium text-gray-700 dark:text-gray-300">Filter Action:</span>
                                <span class="text-gray-600 dark:text-gray-400" id="filterAction">Loading...</span>
                            </div>
                            <div class="text-sm">
                                <span class="font-medium text-gray-700 dark:text-gray-300">Reason:</span>
                                <span class="text-gray-600 dark:text-gray-400" id="automationReason">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Relay Control Panel -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                            <i class="fas fa-plug text-blue-500 mr-3"></i>
                            Relay Control System
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400">Direct control over water quality system components</p>
                    </div>
                    <div class="flex space-x-3">
                        <button id="allOn" class="power-button px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white rounded-xl font-semibold transition-all duration-200 shadow-lg hover:shadow-xl">
                            <i class="fas fa-power-off mr-2"></i>All On
                        </button>
                        <button id="allOff" class="power-button px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white rounded-xl font-semibold transition-all duration-200 shadow-lg hover:shadow-xl">
                            <i class="fas fa-stop mr-2"></i>All Off
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Filter -->
                    <div class="relay-card control-card rounded-2xl p-6 inactive" id="relay1Card">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center">
                                <div class="w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mr-4">
                                    <i class="fas fa-filter text-blue-500 dark:text-blue-400 text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Filter</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Water Filtration</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between mb-6">
                            <span class="text-sm text-gray-600 dark:text-gray-400 font-medium">Status:</span>
                            <span class="status-badge px-3 py-1 rounded-full text-xs font-semibold offline" id="relay1Status">Offline</span>
                        </div>
                        
                        <div class="flex items-center justify-center">
                            <label class="switch">
                                <input type="checkbox" data-relay="1" onchange="toggleRelay(this)">
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="mt-4 text-center">
                            <div class="text-xs text-gray-500 dark:text-gray-400">IN1 - Relay 1</div>
                        </div>
                    </div>

                    <!-- Dispense Water -->
                    <div class="relay-card control-card rounded-2xl p-6 inactive" id="relay2Card">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center">
                                <div class="w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mr-4">
                                    <i class="fas fa-tint text-emerald-500 dark:text-emerald-400 text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Dispense Water</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Water Dispensing</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between mb-6">
                            <span class="text-sm text-gray-600 dark:text-gray-400 font-medium">Status:</span>
                            <span class="status-badge px-3 py-1 rounded-full text-xs font-semibold offline" id="relay2Status">Offline</span>
                        </div>
                        
                        <div class="flex items-center justify-center">
                            <label class="switch">
                                <input type="checkbox" data-relay="2" onchange="toggleRelay(this)">
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="mt-4 text-center">
                            <div class="text-xs text-gray-500 dark:text-gray-400">IN2 - Relay 2</div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
</div>

<!-- Toast Notification Container -->
<div id="toastContainer"></div>

<script>
        let activeRelays = 0;
        
        // Toast notification functions
        function showToast(message, type = 'info', duration = 5000) {
            // Remove emojis from message
            const cleanMessage = message.replace(/[^\w\s\-.,!?()]/g, '');
            
            // Check if there's already a toast of the same type
            const existingToast = document.querySelector(`.toast.${type}`);
            if (existingToast) {
                // Update existing toast instead of creating new one
                existingToast.textContent = cleanMessage;
                return;
            }
            
            const toastContainer = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = cleanMessage;
            
            // Position the toast with proper spacing
            const existingToasts = document.querySelectorAll('.toast');
            const bottomOffset = existingToasts.length * 70; // 70px per toast (height + margin)
            toast.style.bottom = `${20 + bottomOffset}px`;
            
            toastContainer.appendChild(toast);
            
            // Trigger animation
            setTimeout(() => toast.classList.add('show'), 100);
            
            // Auto-remove after duration
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                        // Reposition remaining toasts
                        repositionToasts();
                    }
                }, 300);
            }, duration);
        }
        
        function repositionToasts() {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach((toast, index) => {
                const bottomOffset = index * 70;
                toast.style.bottom = `${20 + bottomOffset}px`;
            });
        }
        
        // Initialize uptime from localStorage or start new session
        function initializeUptime() {
            let startTime = localStorage.getItem('systemStartTime');
            if (!startTime) {
                startTime = Date.now();
                localStorage.setItem('systemStartTime', startTime);
            }
            return parseInt(startTime);
        }
        
        let startTime = initializeUptime();

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
                const cardElement = document.getElementById(`relay${state.relay_number}Card`);
                
                if (checkbox) {
                    checkbox.checked = state.state === 1;
                }
                
                if (statusElement) {
                    statusElement.textContent = state.state === 1 ? 'Online' : 'Offline';
                    statusElement.className = `status-badge px-3 py-1 rounded-full text-xs font-semibold ${state.state === 1 ? 'online' : 'offline'}`;
                }
                
                if (cardElement) {
                    cardElement.className = `relay-card control-card rounded-2xl p-6 ${state.state === 1 ? 'active' : 'inactive'}`;
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

        // Automation functions
        function fetchAutomationData() {
            fetch('../../api/automation_control.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateAutomationUI(data);
                    }
                })
                .catch(error => console.error('Error fetching automation data:', error));
        }

        function updateAutomationUI(data) {
            // Update automation status
            const automationStatus = document.getElementById('automationStatus');
            const tdsStatus = document.getElementById('tdsStatus');
            const turbidityStatus = document.getElementById('turbidityStatus');
            const latestReading = document.getElementById('latestReading');
            const filterAction = document.getElementById('filterAction');
            const automationReason = document.getElementById('automationReason');

            // Update automation settings checkboxes
            if (data.automation_settings) {
                document.getElementById('masterAutomation').checked = data.automation_settings.enabled == 1;
                document.getElementById('filterAutomation').checked = data.automation_settings.filter_auto_enabled == 1;
            }
            
            // Check if manual override is detected and show toast
            if (data.manual_control_detected && data.automation_settings && data.automation_settings.enabled == 0) {
                showToast('Automation cannot be turned ON - Manual relay control is active', 'warning');
            }

            // Update status displays
            if (data.automation_settings) {
                automationStatus.textContent = data.automation_settings.enabled == 1 ? 'Enabled' : 'Disabled';
                automationStatus.className = data.automation_settings.enabled == 1 ? 
                    'text-sm font-medium text-green-600 dark:text-green-400' : 
                    'text-sm font-medium text-red-600 dark:text-red-400';
            }

            if (data.analysis) {
                // Update TDS status
                const tdsStatusText = `${data.analysis.tds_value} ppm (${data.analysis.tds_status})`;
                tdsStatus.textContent = tdsStatusText;
                tdsStatus.className = getStatusClass(data.analysis.tds_status);

                // Update turbidity status
                const turbidityStatusText = `${data.analysis.turbidity_value} NTU (${data.analysis.turbidity_status})`;
                turbidityStatus.textContent = turbidityStatusText;
                turbidityStatus.className = getStatusClass(data.analysis.turbidity_status);

                // Update analysis info
                if (data.sensor_data) {
                    const readingTime = new Date(data.sensor_data.reading_time).toLocaleString();
                    latestReading.textContent = readingTime;
                }

                filterAction.textContent = data.analysis.should_activate_filter ? 'Should Activate' : 'Should Deactivate';
                filterAction.className = data.analysis.should_activate_filter ? 
                    'text-gray-600 dark:text-gray-400 text-green-600 dark:text-green-400' : 
                    'text-gray-600 dark:text-gray-400 text-red-600 dark:text-red-400';

                automationReason.textContent = data.analysis.reason;
            }
        }

        function getStatusClass(status) {
            switch (status) {
                case 'critical':
                    return 'text-sm font-medium text-red-600 dark:text-red-400';
                case 'medium':
                    return 'text-sm font-medium text-yellow-600 dark:text-yellow-400';
                case 'normal':
                    return 'text-sm font-medium text-green-600 dark:text-green-400';
                default:
                    return 'text-sm font-medium text-gray-600 dark:text-gray-400';
            }
        }

        function updateAutomationSettings() {
            const masterEnabled = document.getElementById('masterAutomation').checked ? 1 : 0;
            const filterEnabled = document.getElementById('filterAutomation').checked ? 1 : 0;

            fetch('../../api/automation_control.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_settings&enabled=${masterEnabled}&filter_auto_enabled=${filterEnabled}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Refresh automation data
                    fetchAutomationData();
                    
                    // Show success toast
                    if (masterEnabled) {
                        showToast('Master automation turned ON - Filter automation automatically enabled', 'success');
                    } else {
                        showToast('Master automation turned OFF - Filter automation automatically disabled', 'info');
                    }
                } else {
                    console.error('Error updating automation settings:', data.error);
                    showToast('Failed to update automation settings', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error updating automation settings', 'error');
            });
        }

        function checkAutomationNow() {
            const button = document.getElementById('checkAutomation');
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Checking...';

            fetch('../../api/automation_control.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=check_and_trigger'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Refresh both automation data and relay states
                    fetchAutomationData();
                    fetchRelayStates();
                    
                    // Show success message
                    if (data.action_taken !== 'none') {
                        const message = data.action_taken === 'filter_activated' ? 
                            'Filter activated automatically!' : 
                            'Filter deactivated automatically!';
                        showNotification(message, 'success');
                    } else {
                        showNotification('Automation check completed - no action needed', 'info');
                    }
                } else {
                    console.error('Error checking automation:', data.error);
                    showNotification('Error checking automation', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error checking automation', 'error');
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-sync-alt mr-2"></i>Check Now';
            });
        }

        function showNotification(message, type) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full`;
            
            switch (type) {
                case 'success':
                    notification.className += ' bg-green-500 text-white';
                    break;
                case 'error':
                    notification.className += ' bg-red-500 text-white';
                    break;
                case 'info':
                    notification.className += ' bg-blue-500 text-white';
                    break;
                default:
                    notification.className += ' bg-gray-500 text-white';
            }
            
            notification.textContent = message;
            document.body.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Initialize automation data
        fetchAutomationData();
        setInterval(fetchAutomationData, 10000); // Update every 10 seconds

        // Add event listener for check automation button
        document.getElementById('checkAutomation').addEventListener('click', checkAutomationNow);

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
