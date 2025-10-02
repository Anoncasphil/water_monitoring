<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../config/database.php';

// Include TCPDF library (you'll need to install it)
// For now, I'll create a simple HTML to PDF solution
require_once '../vendor/autoload.php'; // If using Composer for TCPDF

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get user information
    $userQuery = "SELECT first_name, last_name, email FROM users WHERE id = ?";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->bind_param("i", $_SESSION['user_id']);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $user = $userResult->fetch_assoc();
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Get comprehensive data
    $timeRange = $_GET['range'] ?? '30d';
    $days = 30;
    switch ($timeRange) {
        case '24h': $days = 1; break;
        case '7d': $days = 7; break;
        case '30d': $days = 30; break;
        case '90d': $days = 90; break;
    }
    
    // Get latest readings
    $latestQuery = "SELECT * FROM water_readings ORDER BY reading_time DESC LIMIT 1";
    $latestResult = $conn->query($latestQuery);
    $latest = $latestResult->fetch_assoc();
    
    // Get statistics
    $statsQuery = "SELECT 
        COUNT(*) as total_readings,
        AVG(turbidity_ntu) as avg_turbidity,
        AVG(tds_ppm) as avg_tds,
        AVG(ph) as avg_ph,
        AVG(temperature) as avg_temperature,
        MIN(turbidity_ntu) as min_turbidity,
        MAX(turbidity_ntu) as max_turbidity,
        MIN(tds_ppm) as min_tds,
        MAX(tds_ppm) as max_tds,
        MIN(ph) as min_ph,
        MAX(ph) as max_ph,
        MIN(temperature) as min_temp,
        MAX(temperature) as max_temp
        FROM water_readings WHERE reading_time >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->bind_param("i", $days);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
    $stats = $statsResult->fetch_assoc();
    
    // Get hourly data for trends
    $hourlyQuery = "SELECT reading_time, turbidity_ntu, tds_ppm, ph, temperature 
                   FROM water_readings 
                   WHERE reading_time >= DATE_SUB(NOW(), INTERVAL ? DAY) 
                   ORDER BY reading_time";
    $hourlyStmt = $conn->prepare($hourlyQuery);
    $hourlyStmt->bind_param("i", $days);
    $hourlyStmt->execute();
    $hourlyResult = $hourlyStmt->get_result();
    $hourlyData = $hourlyResult->fetch_all(MYSQLI_ASSOC);
    
    // Get daily averages
    $dailyQuery = "SELECT DATE(reading_time) as date, 
                   AVG(turbidity_ntu) as avg_turbidity, 
                   AVG(tds_ppm) as avg_tds, 
                   AVG(ph) as avg_ph, 
                   AVG(temperature) as avg_temperature, 
                   COUNT(*) as readings 
                   FROM water_readings 
                   WHERE reading_time >= DATE_SUB(NOW(), INTERVAL ? DAY) 
                   GROUP BY DATE(reading_time) 
                   ORDER BY date";
    $dailyStmt = $conn->prepare($dailyQuery);
    $dailyStmt->bind_param("i", $days);
    $dailyStmt->execute();
    $dailyResult = $dailyStmt->get_result();
    $dailyData = $dailyResult->fetch_all(MYSQLI_ASSOC);
    
    // Generate comprehensive report
    $exportTime = date('Y-m-d H:i:s');
    $fileName = "water_quality_report_" . date('Y-m-d_H-i-s') . ".html";
    
    // Generate HTML report that can be printed as PDF
    $html = generateReportHTML($user, $stats, $hourlyData, $dailyData, $latest, $timeRange, $exportTime);
    
    // Set headers for HTML download (can be printed as PDF)
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Return the HTML report
    echo $html;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function generateReportHTML($user, $stats, $hourlyData, $dailyData, $latest, $timeRange, $exportTime) {
    $userName = $user['first_name'] . ' ' . $user['last_name'];
    $userEmail = $user['email'];
    
    // Conversion functions
    function convertTurbidityToPercentage($rawValue) {
        return max(0, min(100, (($rawValue - 1) / 2999) * 100));
    }
    
    function convertTDSToPercentage($ppmValue) {
        return max(0, min(100, ($ppmValue / 1000) * 100));
    }
    
    // Quality assessment functions
    function getTurbidityQuality($ntu) {
        if ($ntu <= 2) return ['status' => 'Good', 'color' => 'green'];
        if ($ntu <= 5) return ['status' => 'Medium', 'color' => 'yellow'];
        return ['status' => 'Critical', 'color' => 'red'];
    }
    
    function getPHQuality($ph) {
        if ($ph >= 6 && $ph <= 8) return ['status' => 'Good', 'color' => 'green'];
        if (($ph >= 4 && $ph < 6) || ($ph > 8 && $ph <= 10)) return ['status' => 'Medium', 'color' => 'yellow'];
        return ['status' => 'Critical', 'color' => 'red'];
    }
    
    function getTemperatureQuality($temp) {
        if ($temp >= 20 && $temp < 30) return ['status' => 'Good', 'color' => 'green'];
        if ($temp >= 0 && $temp < 20) return ['status' => 'Cold', 'color' => 'blue'];
        if ($temp >= 30 && $temp <= 40) return ['status' => 'Warm', 'color' => 'orange'];
        return ['status' => 'Unknown', 'color' => 'gray'];
    }
    
    $latestTurbidityQuality = getTurbidityQuality($latest['turbidity_ntu']);
    $latestPHQuality = getPHQuality($latest['ph']);
    $latestTempQuality = getTemperatureQuality($latest['temperature']);
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Water Quality Analytics Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #007bff; padding-bottom: 20px; }
            .header h1 { color: #007bff; margin: 0; }
            .header h2 { color: #666; margin: 5px 0; font-weight: normal; }
            .report-info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            .section { margin-bottom: 30px; }
            .section h3 { color: #007bff; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
            .metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
            .metric-card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 15px; text-align: center; }
            .metric-value { font-size: 24px; font-weight: bold; margin: 5px 0; }
            .metric-label { color: #666; font-size: 14px; }
            .quality-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; color: white; font-size: 12px; font-weight: bold; }
            .quality-good { background-color: #28a745; }
            .quality-medium { background-color: #ffc107; color: #000; }
            .quality-critical { background-color: #dc3545; }
            .quality-cold { background-color: #17a2b8; }
            .quality-warm { background-color: #fd7e14; }
            .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            .table th { background-color: #f8f9fa; font-weight: bold; }
            .summary-box { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 15px 0; }
            .footer { margin-top: 40px; text-align: center; color: #666; font-size: 12px; border-top: 1px solid #ddd; padding-top: 20px; }
            .print-button { 
                position: fixed; 
                top: 20px; 
                right: 20px; 
                background: #007bff; 
                color: white; 
                border: none; 
                padding: 12px 20px; 
                border-radius: 5px; 
                cursor: pointer; 
                font-size: 14px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                z-index: 1000;
            }
            .print-button:hover { background: #0056b3; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
                .print-button { display: none; }
                .header { page-break-after: avoid; }
                .section { page-break-inside: avoid; }
            }
        </style>
    </head>
    <body>
        <button class="print-button" onclick="window.print()">
            📄 Print to PDF
        </button>
        
        <div class="header">
            <h1>Water Quality Analytics Report</h1>
            <h2>Comprehensive Data Analysis and Summary</h2>
        </div>
        
        <div class="report-info">
            <strong>Report Generated By:</strong> ' . htmlspecialchars($userName) . ' (' . htmlspecialchars($userEmail) . ')<br>
            <strong>Export Time:</strong> ' . htmlspecialchars($exportTime) . '<br>
            <strong>Time Period:</strong> Last ' . $timeRange . '<br>
            <strong>Report ID:</strong> WQR-' . date('Ymd-His') . '
        </div>
        
        <div class="section">
            <h3>Executive Summary</h3>
            <div class="summary-box">
                <p><strong>Latest Water Quality Status:</strong></p>
                <ul>
                    <li><strong>Turbidity:</strong> ' . number_format($latest['turbidity_ntu'], 1) . ' NTU 
                        (' . number_format(convertTurbidityToPercentage($latest['turbidity_ntu']), 1) . '%) 
                        - <span class="quality-badge quality-' . $latestTurbidityQuality['color'] . '">' . $latestTurbidityQuality['status'] . '</span></li>
                    <li><strong>TDS:</strong> ' . number_format($latest['tds_ppm'], 0) . ' ppm 
                        (' . number_format(convertTDSToPercentage($latest['tds_ppm']), 1) . '%)</li>
                    <li><strong>pH:</strong> ' . number_format($latest['ph'], 2) . ' 
                        - <span class="quality-badge quality-' . $latestPHQuality['color'] . '">' . $latestPHQuality['status'] . '</span></li>
                    <li><strong>Temperature:</strong> ' . number_format($latest['temperature'], 1) . '°C 
                        - <span class="quality-badge quality-' . $latestTempQuality['color'] . '">' . $latestTempQuality['status'] . '</span></li>
                </ul>
            </div>
        </div>
        
        <div class="section">
            <h3>Key Performance Metrics</h3>
            <div class="metric-grid">
                <div class="metric-card">
                    <div class="metric-label">Total Readings</div>
                    <div class="metric-value" style="color: #007bff;">' . number_format($stats['total_readings']) . '</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Average Turbidity</div>
                    <div class="metric-value" style="color: #28a745;">' . number_format($stats['avg_turbidity'], 2) . ' NTU</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Average TDS</div>
                    <div class="metric-value" style="color: #17a2b8;">' . number_format($stats['avg_tds'], 0) . ' ppm</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Average pH</div>
                    <div class="metric-value" style="color: #6f42c1;">' . number_format($stats['avg_ph'], 2) . '</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label">Average Temperature</div>
                    <div class="metric-value" style="color: #dc3545;">' . number_format($stats['avg_temperature'], 1) . '°C</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h3>Parameter Ranges</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Minimum</th>
                        <th>Maximum</th>
                        <th>Range</th>
                        <th>Standard Deviation</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Turbidity (NTU)</strong></td>
                        <td>' . number_format($stats['min_turbidity'], 2) . '</td>
                        <td>' . number_format($stats['max_turbidity'], 2) . '</td>
                        <td>' . number_format($stats['max_turbidity'] - $stats['min_turbidity'], 2) . '</td>
                        <td>±' . number_format(($stats['max_turbidity'] - $stats['min_turbidity']) / 2, 2) . '</td>
                    </tr>
                    <tr>
                        <td><strong>TDS (ppm)</strong></td>
                        <td>' . number_format($stats['min_tds'], 0) . '</td>
                        <td>' . number_format($stats['max_tds'], 0) . '</td>
                        <td>' . number_format($stats['max_tds'] - $stats['min_tds'], 0) . '</td>
                        <td>±' . number_format(($stats['max_tds'] - $stats['min_tds']) / 2, 0) . '</td>
                    </tr>
                    <tr>
                        <td><strong>pH</strong></td>
                        <td>' . number_format($stats['min_ph'], 2) . '</td>
                        <td>' . number_format($stats['max_ph'], 2) . '</td>
                        <td>' . number_format($stats['max_ph'] - $stats['min_ph'], 2) . '</td>
                        <td>±' . number_format(($stats['max_ph'] - $stats['min_ph']) / 2, 2) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Temperature (°C)</strong></td>
                        <td>' . number_format($stats['min_temp'], 1) . '</td>
                        <td>' . number_format($stats['max_temp'], 1) . '</td>
                        <td>' . number_format($stats['max_temp'] - $stats['min_temp'], 1) . '</td>
                        <td>±' . number_format(($stats['max_temp'] - $stats['min_temp']) / 2, 1) . '</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h3>Recent Measurements (Last 10 Readings)</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Turbidity</th>
                        <th>TDS</th>
                        <th>pH</th>
                        <th>Temperature</th>
                    </tr>
                </thead>
                <tbody>';
    
    $recentReadings = array_slice($hourlyData, -10);
    foreach ($recentReadings as $reading) {
        $turbidityPercent = convertTurbidityToPercentage($reading['turbidity_ntu']);
        $tdsPercent = convertTDSToPercentage($reading['tds_ppm']);
        $html .= '
                    <tr>
                        <td>' . date('M j, H:i', strtotime($reading['reading_time'])) . '</td>
                        <td>' . number_format($reading['turbidity_ntu'], 1) . ' NTU<br><small>(' . number_format($turbidityPercent, 1) . '%)</small></td>
                        <td>' . number_format($reading['tds_ppm'], 0) . ' ppm<br><small>(' . number_format($tdsPercent, 1) . '%)</small></td>
                        <td>' . number_format($reading['ph'], 2) . '</td>
                        <td>' . number_format($reading['temperature'], 1) . '°C</td>
                    </tr>';
    }
    
    $html .= '
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h3>Daily Averages Summary</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Avg Turbidity</th>
                        <th>Avg TDS</th>
                        <th>Avg pH</th>
                        <th>Avg Temperature</th>
                        <th>Readings</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($dailyData as $day) {
        $html .= '
                    <tr>
                        <td>' . date('M j, Y', strtotime($day['date'])) . '</td>
                        <td>' . number_format($day['avg_turbidity'], 2) . ' NTU</td>
                        <td>' . number_format($day['avg_tds'], 0) . ' ppm</td>
                        <td>' . number_format($day['avg_ph'], 2) . '</td>
                        <td>' . number_format($day['avg_temperature'], 1) . '°C</td>
                        <td>' . $day['readings'] . '</td>
                    </tr>';
    }
    
    $html .= '
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h3>Quality Assessment Guidelines</h3>
            <div class="summary-box">
                <h4>Turbidity Standards:</h4>
                <ul>
                    <li><span class="quality-badge quality-green">Good</span>: ≤ 2 NTU (Clear water)</li>
                    <li><span class="quality-badge quality-yellow">Medium</span>: 2-5 NTU (Slightly cloudy)</li>
                    <li><span class="quality-badge quality-critical">Critical</span>: > 5 NTU (Very cloudy)</li>
                </ul>
                
                <h4>pH Standards:</h4>
                <ul>
                    <li><span class="quality-badge quality-green">Good</span>: 6.0-8.0 (Optimal range)</li>
                    <li><span class="quality-badge quality-yellow">Medium</span>: 4.0-6.0 & 8.0-10.0 (Acceptable)</li>
                    <li><span class="quality-badge quality-critical">Critical</span>: < 4.0 or > 10.0 (Requires attention)</li>
                </ul>
                
                <h4>Temperature Standards:</h4>
                <ul>
                    <li><span class="quality-badge quality-green">Good</span>: 20-30°C (Optimal temperature)</li>
                    <li><span class="quality-badge quality-blue">Cold</span>: 0-20°C (Cool water)</li>
                    <li><span class="quality-badge quality-warm">Warm</span>: 30-40°C (Warm water)</li>
                </ul>
            </div>
        </div>
        
        <div class="footer">
            <p>This report was automatically generated by the Water Quality Monitoring System</p>
            <p>Generated on ' . $exportTime . ' by ' . htmlspecialchars($userName) . '</p>
            <p>For technical support or questions about this report, please contact the system administrator.</p>
        </div>
        
        <script>
            // Auto-trigger print dialog when page loads
            window.addEventListener(\'load\', function() {
                setTimeout(function() {
                    if (confirm(\'Would you like to print this report to PDF?\')) {
                        window.print();
                    }
                }, 1000);
            });
            
            // Add keyboard shortcut for printing
            document.addEventListener(\'keydown\', function(e) {
                if (e.ctrlKey && e.key === \'p\') {
                    e.preventDefault();
                    window.print();
                }
            });
        </script>
    </body>
    </html>';
}
?>
