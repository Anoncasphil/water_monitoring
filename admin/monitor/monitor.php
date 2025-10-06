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
        
        /* Responsive improvements */
        @media (max-width: 768px) {
            .lg\:ml-64 {
                margin-left: 0;
            }
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            .grid-cols-1.md\:grid-cols-2.lg\:grid-cols-4 {
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
            .sensor-card {
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
        .sensor-card {
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
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-10">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                        <i class="fas fa-tachometer-alt text-blue-500 mr-3"></i>
                        Water Quality Monitor
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 text-lg">Real-time sensor monitoring and quality assessment</p>
                </div>
                <div class="flex items-center flex-wrap gap-4 md:gap-6">
                    <div class="flex items-center space-x-2">
                        <div class="status-indicator bg-green-500"></div>
                        <span class="text-sm font-medium text-green-600 dark:text-green-400">System Online</span>
                    </div>
                    <button id="themeToggle" class="p-3 rounded-xl bg-white dark:bg-gray-800 shadow-lg hover:shadow-xl transition-all duration-200 shrink-0">
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
                        <div class="text-lg text-gray-500 dark:text-gray-400 mb-2">%</div>
                        <div class="text-xs text-blue-500 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 px-3 py-1 rounded-full inline-block" id="turbidityNTU">
                            <i class="fas fa-filter mr-1"></i>-- NTU
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
                        <div class="text-lg text-gray-500 dark:text-gray-400 mb-2">%</div>
                        <div class="text-xs text-emerald-500 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 px-3 py-1 rounded-full inline-block" id="tdsPPM">
                            <i class="fas fa-flask mr-1"></i>-- ppm
                        </div>
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
                            <span class="text-gray-500 dark:text-gray-400">Cold</span>
                            <span class="text-blue-600 dark:text-blue-400 font-medium">0-20°C</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Good</span>
                            <span class="text-green-600 dark:text-green-400 font-medium">20-30°C</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Warm</span>
                            <span class="text-orange-600 dark:text-orange-400 font-medium">30-40°C</span>
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

    <!-- Acknowledgment Modal (matches dashboard UX) -->
    <div id="acknowledgeModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-4 sm:p-6 border border-gray-200 dark:border-gray-700 w-11/12 sm:w-full max-w-md sm:max-w-lg shadow-2xl rounded-xl bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                        <i class="fas fa-shield-alt text-amber-500 mr-3"></i>
                        Acknowledge Alert
                    </h3>
                    <button id="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                <div id="modalAlertDetails" class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg"></div>
                <form id="acknowledgeForm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Action Taken <span class="text-red-500">*</span></label>
                        <select id="actionTaken" name="action_taken" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                            <option value="">Select an action...</option>
                            <option value="investigated">Investigated Issue</option>
                            <option value="corrected">Corrected Problem</option>
                            <option value="monitoring">Monitoring Closely</option>
                            <option value="maintenance">Scheduled Maintenance</option>
                            <option value="reported">Reported to Supervisor</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Additional Details</label>
                        <textarea id="acknowledgeDetails" name="details" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500" placeholder="Describe what was done to address this alert..."></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Responsible Person <span class="text-red-500">*</span></label>
                        <input type="text" id="responsiblePerson" name="responsible_person" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500" placeholder="Enter your name">
                    </div>
                    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="button" id="cancelAcknowledge" class="px-6 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">Cancel</button>
                        <button type="submit" id="submitAcknowledge" class="px-6 py-2.5 text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 dark:bg-amber-700 dark:hover:bg-amber-600 rounded-lg transition-colors shadow-sm"><i class="fas fa-shield-alt mr-2"></i>Acknowledge Alert</button>
                    </div>
                </form>
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
            // Temperature categories: Cold, Good, Warm
            if (value >= 0 && value < 20) return { status: 'Cold', color: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' };
            if (value >= 20 && value < 30) return { status: 'Good', color: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' };
            if (value >= 30 && value <= 40) return { status: 'Warm', color: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400' };
            return { status: 'Unknown', color: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400' };
        }

        // --- Acknowledgment system (mirrors dashboard) ---
        let unacknowledgedAlerts = new Map();
        let acknowledgedAlerts = new Set();
        let currentAlertData = null;
        const ACK_DURATION_MINUTES = 300; // 5 hours
        const ACK_STORAGE_KEY = 'sensorAcknowledgments';
        const ACK_RESET_KEY = 'sensorAckResetOverrides';

        function readAckStorage() {
            try { const raw = localStorage.getItem(ACK_STORAGE_KEY); return raw ? JSON.parse(raw) : {}; } catch (_) { return {}; }
        }
        function writeAckStorage(map) {
            try { localStorage.setItem(ACK_STORAGE_KEY, JSON.stringify(map)); } catch (_) {}
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
        function readAckReset() {
            try { const raw = localStorage.getItem(ACK_RESET_KEY); return raw ? JSON.parse(raw) : {}; } catch (_) { return {}; }
        }
        function writeAckReset(map) {
            try { localStorage.setItem(ACK_RESET_KEY, JSON.stringify(map)); } catch (_) {}
        }
        function isResetActive(sensorType) {
            const map = readAckReset();
            const now = Date.now();
            if (map[sensorType] && typeof map[sensorType].expiresAt === 'number') {
                if (map[sensorType].expiresAt > now) return true;
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
        async function loadAcknowledgedAlerts() {
            try {
                const resp = await fetch('../../api/get_ack_status.php', { headers: { 'Accept':'application/json' }, cache: 'no-store' });
                const data = await resp.json();
                if (data.success && data.data) {
                    acknowledgedAlerts.clear();
                    Object.keys(data.data).forEach(sensor => acknowledgedAlerts.add(sensor));
                }
            } catch (e) { console.error('Monitor loadAcknowledgedAlerts error:', e); }
        }
        async function submitAcknowledgment(alertType, alertMessage, actionTaken, details, responsiblePerson, values) {
            try {
                const resp = await fetch('../../api/acknowledge_alert.php', {
                    method: 'POST', headers: { 'Content-Type':'application/json' },
                    body: JSON.stringify({ alert_type: alertType, alert_message: alertMessage, action_taken: actionTaken, details, responsible_person: responsiblePerson, timestamp: new Date().toISOString(), values })
                });
                const data = await resp.json();
                return data.success;
            } catch (e) { console.error('Monitor submitAcknowledgment error:', e); return false; }
        }
        function clearAcknowledgment(sensorType) {
            try {
                const ackMap = readAckStorage();
                if (ackMap[sensorType]) { delete ackMap[sensorType]; writeAckStorage(ackMap); }
                acknowledgedAlerts.delete(sensorType);
                setReset(sensorType);
                updateData();
            } catch (e) { console.error('Monitor clearAcknowledgment error:', e); }
        }

        // --- Sound notifications ---
        let lastSoundLevel = null; // 'critical' | 'warning' | null
        let lastSoundTime = 0;
        function playAlertSound(level) {
            const now = Date.now();
            // Debounce: play at most every 15s per level change
            if (lastSoundLevel === level && now - lastSoundTime < 15000) return;
            lastSoundLevel = level; lastSoundTime = now;

            playEnhancedAlertSound(level);
            // Removed showVisualAlert(level) - no more toast notifications
        }

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
                        document.getElementById('turbidityValue').textContent = turbidityPercent.toFixed(1);
                        document.getElementById('tdsValue').textContent = tdsPercent.toFixed(1);
                        document.getElementById('phValue').textContent = parseFloat(latest.ph).toFixed(1);
                        document.getElementById('temperatureValue').textContent = parseFloat(latest.temperature).toFixed(1);
                        
                        // Update raw value displays
                        document.getElementById('turbidityNTU').innerHTML = `<i class="fas fa-filter mr-1"></i>${parseFloat(latest.turbidity_ntu).toFixed(0)} NTU`;
                        document.getElementById('tdsPPM').innerHTML = `<i class="fas fa-flask mr-1"></i>${parseFloat(latest.tds_ppm).toFixed(0)} ppm`;

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

                        // Last updated UI removed

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
            if (turbidity <= 2) { alerts.push({ type: 'success', title: 'Turbidity', message: `Good water clarity (${turbidityPercent.toFixed(1)}%, ${turbidity.toFixed(0)} NTU)`, icon: 'fa-check-circle' }); overallScore++; }
            else if (turbidity <= 5) { alerts.push({ type: 'warning', title: 'Turbidity', message: `Medium clarity - monitor closely (${turbidityPercent.toFixed(1)}%, ${turbidity.toFixed(0)} NTU)`, icon: 'fa-exclamation-triangle' }); overallScore += 0.5; }
            else { alerts.push({ type: 'danger', title: 'Turbidity', message: `Critical clarity - requires attention (${turbidityPercent.toFixed(1)}%, ${turbidity.toFixed(0)} NTU)`, icon: 'fa-exclamation-circle' }); }

            if (tdsPercent <= 30) { alerts.push({ type: 'success', title: 'TDS', message: `Good dissolved solids content (${tdsPercent.toFixed(1)}%, ${tds.toFixed(0)} ppm)`, icon: 'fa-check-circle' }); overallScore++; }
            else if (tdsPercent <= 60) { alerts.push({ type: 'warning', title: 'TDS', message: `Medium dissolved solids (${tdsPercent.toFixed(1)}%, ${tds.toFixed(0)} ppm)`, icon: 'fa-exclamation-triangle' }); overallScore += 0.5; }
            else { alerts.push({ type: 'danger', title: 'TDS', message: `High dissolved solids - treatment needed (${tdsPercent.toFixed(1)}%, ${tds.toFixed(0)} ppm)`, icon: 'fa-exclamation-circle' }); }

            if (ph >= 6 && ph <= 8) { alerts.push({ type: 'success', title: 'pH Level', message: 'Good pH range', icon: 'fa-check-circle' }); overallScore++; }
            else if ((ph >= 4 && ph < 6) || (ph > 8 && ph <= 10)) { alerts.push({ type: 'warning', title: 'pH Level', message: 'Medium pH - monitor and adjust as needed', icon: 'fa-exclamation-triangle' }); overallScore += 0.5; }
            else { alerts.push({ type: 'danger', title: 'pH Level', message: 'Critical pH - immediate adjustment needed', icon: 'fa-exclamation-circle' }); }

            if (temperature >= 0 && temperature < 20) { alerts.push({ type: 'warning', title: 'Temperature', message: 'Cold water - temperature is cool', icon: 'fa-snowflake' }); overallScore += 0.5; }
            else if (temperature >= 20 && temperature < 30) { alerts.push({ type: 'success', title: 'Temperature', message: 'Good water temperature', icon: 'fa-check-circle' }); overallScore++; }
            else if (temperature >= 30 && temperature <= 40) { alerts.push({ type: 'warning', title: 'Temperature', message: 'Warm water - temperature is warm', icon: 'fa-fire' }); overallScore += 0.5; }

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

            // Determine sound severity excluding acknowledged sensors
            const soundRelevant = alerts.filter(a => {
                const title = a.title || '';
                const sensor = title.toLowerCase().includes('turbidity') ? 'turbidity' :
                               title.toLowerCase().includes('tds') ? 'tds' :
                               title.toLowerCase().includes('ph') ? 'ph' : null;
                if (!sensor) return false;
                return !(acknowledgedAlerts.has(sensor) || isSensorAcknowledged(sensor));
            });
            const hasCritical = soundRelevant.some(a => a.type === 'danger');
            const hasWarning = soundRelevant.some(a => a.type === 'warning');
            if (hasCritical) playAlertSound('critical'); else if (hasWarning) playAlertSound('warning');

            // Generate alert cards with acknowledgment actions (turbidity/tds/pH only)
            alertsContainer.innerHTML = alerts.map(alert => {
                const title = alert.title || '';
                const sensorType = title.toLowerCase().includes('turbidity') ? 'turbidity' :
                                   title.toLowerCase().includes('tds') ? 'tds' :
                                   title.toLowerCase().includes('ph') ? 'ph' : null;
                const needsAck = sensorType && (alert.type === 'danger' || (sensorType !== 'turbidity' && alert.type === 'warning'));
                const isAck = sensorType && (acknowledgedAlerts.has(sensorType) || isSensorAcknowledged(sensorType));
                return `
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
                        <div class="flex items-center justify-between mb-1">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">${alert.title}</h3>
                            ${needsAck ? `
                                ${isAck ? `
                                    <span class="inline-flex items-center space-x-2">
                                        <span class="px-3 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 rounded-lg">
                                            <i class="fas fa-check-circle mr-1"></i>Acknowledged (5h)
                                        </span>
                                        <button class="px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600" onclick="clearAcknowledgment('${sensorType}')">Reset</button>
                                    </span>
                                ` : `
                                    <button class="px-3 py-1 text-xs font-medium text-white bg-amber-600 hover:bg-amber-700 dark:bg-amber-700 dark:hover:bg-amber-600 rounded-lg" onclick="openAcknowledgeModal('${sensorType}', '${alert.message.replace(/'/g, "\'")}', '${alert.title.replace(/'/g, "\'")}')">
                                        <i class="fas fa-shield-alt mr-1"></i>Acknowledge
                                    </button>
                                `}
                            ` : ''}
                        </div>
                        <p class="text-gray-600 dark:text-gray-400">${alert.message}</p>
                    </div>
                </div>`;
            }).join('');
        }

        // Update data every 5 seconds
        let ackEvtSrc = null;
        function connectAckSSE() {
            try {
                if (ackEvtSrc) { try { ackEvtSrc.close(); } catch(_){} }
                ackEvtSrc = new EventSource('../../api/ack_events.php');
                let sseReady = false;
                const sseFallbackTimer = setTimeout(() => {
                    if (!sseReady) {
                        if (!window.__ackPoll) {
                            window.__ackPoll = setInterval(() => { updateData(); }, 3000);
                        }
                    }
                }, 6000);
                ackEvtSrc.onopen = () => { sseReady = true; if (window.__ackPoll) { clearInterval(window.__ackPoll); window.__ackPoll = null; } };
                ackEvtSrc.addEventListener('ack', () => { updateData(); });
                ackEvtSrc.onerror = () => { try { ackEvtSrc.close(); } catch(_){}; setTimeout(connectAckSSE, 3000); };
            } catch (_) { setTimeout(connectAckSSE, 5000); }
        }

        (async () => {
            clearExpiredAcknowledgments();
            await loadAcknowledgedAlerts();
            updateData();
            connectAckSSE();
        })();
        const ACK_POLL_INTERVAL_MS = 10000; // faster cross-device reflection
        setInterval(() => { clearExpiredAcknowledgments(); loadAcknowledgedAlerts(); updateData(); }, ACK_POLL_INTERVAL_MS);

        // Cross-tab sync: reflect acks instantly across browser tabs on same device
        window.addEventListener('storage', (e) => {
            if (e.key === ACK_STORAGE_KEY || e.key === ACK_RESET_KEY) {
                try {
                    const localAcks = readAckStorage();
                    Object.keys(localAcks).forEach(sensorType => acknowledgedAlerts.add(sensorType));
                } catch (_) {}
                updateData();
            }
        });

        // Toast notification (bottom-right)
        function showNotification(message, type = 'info') {
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
            notification.style.transform = 'translateX(100%)';
            document.body.appendChild(notification);
            setTimeout(() => { notification.style.transform = 'translateX(0)'; }, 10);
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => { if (notification.parentNode) notification.parentNode.removeChild(notification); }, 300);
            }, 4000);
        }

        // Modal helpers
        function openAcknowledgeModal(sensorType, message, title) {
            currentAlertData = { sensorType, message, title, timestamp: new Date() };
            const details = document.getElementById('modalAlertDetails');
            details.innerHTML = `
                <div class="text-sm">
                    <div class="font-semibold text-amber-800 dark:text-amber-200 mb-3 flex items-center">
                        <i class="fas fa-info-circle mr-2"></i>Alert Details
                    </div>
                    <div class="mb-3 p-3 bg-amber-100 dark:bg-amber-900/30 rounded-lg border border-amber-200 dark:border-amber-800">
                        <div class="text-amber-900 dark:text-amber-100 font-medium leading-relaxed">${message}</div>
                    </div>
                    <div class="text-xs text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 px-2 py-1 rounded border border-amber-200 dark:border-amber-800">
                        <i class="fas fa-clock mr-1"></i>Detected at: ${new Date().toLocaleString()}
                    </div>
                </div>`;
            document.getElementById('acknowledgeForm').reset();
            document.getElementById('acknowledgeModal').classList.remove('hidden');
        }
        function closeAcknowledgeModal() { document.getElementById('acknowledgeModal').classList.add('hidden'); currentAlertData = null; }
        document.getElementById('closeModal').addEventListener('click', closeAcknowledgeModal);
        document.getElementById('cancelAcknowledge').addEventListener('click', closeAcknowledgeModal);
        document.getElementById('acknowledgeModal').addEventListener('click', function(e){ if (e.target === this) closeAcknowledgeModal(); });
        document.getElementById('acknowledgeForm').addEventListener('submit', function(e){
            e.preventDefault(); if (!currentAlertData) return;
            const fd = new FormData(this);
            submitAcknowledgment(currentAlertData.sensorType, currentAlertData.message, fd.get('action_taken'), fd.get('details'), fd.get('responsible_person'), {})
                .then(ok => {
                    if (ok) {
                        acknowledgedAlerts.add(currentAlertData.sensorType);
                        setSensorAcknowledged(currentAlertData.sensorType);
                        showNotification('Alert acknowledged successfully', 'success');
                        updateData();
                        closeAcknowledgeModal();
                    } else {
                        showNotification('Failed to acknowledge alert', 'error');
                    }
                })
                .catch(() => showNotification('Error acknowledging alert', 'error'));
        });

        // Dark mode toggle
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;

        themeToggle.addEventListener('click', () => {
            html.classList.toggle('dark');
            localStorage.theme = html.classList.contains('dark') ? 'dark' : 'light';
        });
    </script>
</body>
</html>
