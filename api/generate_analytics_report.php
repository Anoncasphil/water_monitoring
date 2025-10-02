<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    require_once '../config/database.php';
    require_once '../vendor/autoload.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration error: ' . $e->getMessage()]);
    exit;
}

// Use TCPDF for PDF generation

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
    
    if (!$latest) {
        throw new Exception('No water quality data found in the database');
    }
    
    // Get statistics
    $statsQuery = "SELECT 
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
        FROM water_readings WHERE reading_time >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->bind_param("i", $days);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
    $stats = $statsResult->fetch_assoc();
    
    // Get hourly data for trends
    $hourlyQuery = "SELECT reading_time, turbidity, tds, ph, temperature 
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
                   AVG(turbidity) as avg_turbidity, 
                   AVG(tds) as avg_tds, 
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
    
    // Get hourly statistics for trends
    $hourlyStatsQuery = "SELECT 
        HOUR(reading_time) as hour,
        AVG(turbidity) as avg_turbidity,
        AVG(tds) as avg_tds,
        AVG(ph) as avg_ph,
        AVG(temperature) as avg_temperature,
        COUNT(*) as readings
        FROM water_readings 
        WHERE reading_time >= DATE_SUB(NOW(), INTERVAL ? DAY) 
        GROUP BY HOUR(reading_time) 
        ORDER BY hour";
    $hourlyStatsStmt = $conn->prepare($hourlyStatsQuery);
    $hourlyStatsStmt->bind_param("i", $days);
    $hourlyStatsStmt->execute();
    $hourlyStatsResult = $hourlyStatsStmt->get_result();
    $hourlyStatsData = $hourlyStatsResult->fetch_all(MYSQLI_ASSOC);
    
    // Get quality distribution statistics
    $qualityStatsQuery = "SELECT 
        SUM(CASE WHEN turbidity <= 2 THEN 1 ELSE 0 END) as good_turbidity,
        SUM(CASE WHEN turbidity > 2 AND turbidity <= 5 THEN 1 ELSE 0 END) as medium_turbidity,
        SUM(CASE WHEN turbidity > 5 THEN 1 ELSE 0 END) as critical_turbidity,
        SUM(CASE WHEN ph >= 6 AND ph <= 8 THEN 1 ELSE 0 END) as good_ph,
        SUM(CASE WHEN (ph >= 4 AND ph < 6) OR (ph > 8 AND ph <= 10) THEN 1 ELSE 0 END) as medium_ph,
        SUM(CASE WHEN ph < 4 OR ph > 10 THEN 1 ELSE 0 END) as critical_ph,
        SUM(CASE WHEN temperature >= 20 AND temperature < 30 THEN 1 ELSE 0 END) as good_temp,
        SUM(CASE WHEN temperature >= 0 AND temperature < 20 THEN 1 ELSE 0 END) as cold_temp,
        SUM(CASE WHEN temperature >= 30 AND temperature <= 40 THEN 1 ELSE 0 END) as warm_temp
        FROM water_readings 
        WHERE reading_time >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $qualityStatsStmt = $conn->prepare($qualityStatsQuery);
    $qualityStatsStmt->bind_param("i", $days);
    $qualityStatsStmt->execute();
    $qualityStatsResult = $qualityStatsStmt->get_result();
    $qualityStats = $qualityStatsResult->fetch_assoc();
    
    // Get alerts and acknowledgments data
    $alertsQuery = "SELECT 
        COUNT(*) as total_alerts,
        SUM(CASE WHEN acknowledged_at IS NOT NULL THEN 1 ELSE 0 END) as acknowledged_alerts,
        alert_type,
        COUNT(*) as count_by_type
        FROM alert_acknowledgments 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
        GROUP BY alert_type";
    $alertsStmt = $conn->prepare($alertsQuery);
    $alertsStmt->bind_param("i", $days);
    $alertsStmt->execute();
    $alertsResult = $alertsStmt->get_result();
    $alertsData = $alertsResult->fetch_all(MYSQLI_ASSOC);
    
    // Generate comprehensive PDF report
    $exportTime = date('Y-m-d H:i:s');
    $fileName = "water_quality_report_" . date('Y-m-d_H-i-s') . ".pdf";
    
    // Generate PDF report using TCPDF
    generatePDFReport($user, $stats, $hourlyData, $dailyData, $latest, $timeRange, $exportTime, $fileName, $hourlyStatsData, $qualityStats, $alertsData, $days);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function generatePDFReport($user, $stats, $hourlyData, $dailyData, $latest, $timeRange, $exportTime, $fileName, $hourlyStatsData, $qualityStats, $alertsData, $days) {
    $userName = $user['first_name'] . ' ' . $user['last_name'];
    $userEmail = $user['email'];
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Water Quality Monitoring System');
    $pdf->SetAuthor($userName);
    $pdf->SetTitle('Water Quality Analytics Report');
    $pdf->SetSubject('Water Quality Data Analysis');
    $pdf->SetKeywords('Water Quality, Analytics, Report, Monitoring');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'Water Quality Analytics Report', 'Generated by: ' . $userName . ' on ' . $exportTime);
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    
    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Add a page
    $pdf->AddPage();
    
    // Conversion functions
    function convertTurbidityToPercentage($rawValue) {
        return max(0, min(100, (($rawValue - 1) / 2999) * 100));
    }
    
    function convertTDSToPercentage($ppmValue) {
        return max(0, min(100, ($ppmValue / 1000) * 100));
    }
    
    // Quality assessment functions
    function getTurbidityQuality($ntu) {
        if ($ntu <= 2) return ['status' => 'Good', 'description' => 'Excellent water clarity'];
        if ($ntu <= 5) return ['status' => 'Medium', 'description' => 'Acceptable but slightly cloudy'];
        return ['status' => 'Critical', 'description' => 'Poor water quality - requires immediate attention'];
    }
    
    function getPHQuality($ph) {
        if ($ph >= 6 && $ph <= 8) return ['status' => 'Good', 'description' => 'Optimal pH range for most applications'];
        if (($ph >= 4 && $ph < 6) || ($ph > 8 && $ph <= 10)) return ['status' => 'Medium', 'description' => 'Acceptable but may need adjustment'];
        return ['status' => 'Critical', 'description' => 'Extreme pH - immediate correction required'];
    }
    
    function getTemperatureQuality($temp) {
        if ($temp >= 20 && $temp < 30) return ['status' => 'Good', 'description' => 'Ideal temperature range'];
        if ($temp >= 0 && $temp < 20) return ['status' => 'Cold', 'description' => 'Cool water temperature'];
        if ($temp >= 30 && $temp <= 40) return ['status' => 'Warm', 'description' => 'Warm water temperature'];
        return ['status' => 'Unknown', 'description' => 'Temperature outside normal range'];
    }
    
    function getTDSQuality($tds) {
        if ($tds <= 300) return ['status' => 'Good', 'description' => 'Low TDS - excellent water quality'];
        if ($tds <= 600) return ['status' => 'Medium', 'description' => 'Moderate TDS - acceptable levels'];
        return ['status' => 'High', 'description' => 'High TDS - may affect taste and equipment'];
    }
    
    // Calculate additional statistics
    function calculateCoefficientOfVariation($mean, $stdDev) {
        return $mean > 0 ? ($stdDev / $mean) * 100 : 0;
    }
    
    function getTrendDirection($values) {
        if (count($values) < 2) return 'Insufficient Data';
        $first = array_slice($values, 0, count($values)/2);
        $second = array_slice($values, count($values)/2);
        $firstAvg = array_sum($first) / count($first);
        $secondAvg = array_sum($second) / count($second);
        $change = (($secondAvg - $firstAvg) / $firstAvg) * 100;
        
        if ($change > 5) return 'Increasing';
        if ($change < -5) return 'Decreasing';
        return 'Stable';
    }
    
    // Title
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->Cell(0, 12, 'WATER QUALITY MONITORING SYSTEM', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Comprehensive Analytics Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, 'Detailed Analysis & Performance Assessment', 0, 1, 'C');
    $pdf->Ln(8);
    
    // Report Information Box
    $pdf->SetFillColor(240, 248, 255);
    $pdf->Rect(15, $pdf->GetY(), 180, 25, 'F');
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetXY(20, $pdf->GetY() + 2);
    $pdf->Cell(0, 6, 'REPORT INFORMATION', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetXY(20, $pdf->GetY());
    $pdf->Cell(90, 4, 'Generated By: ' . $userName, 0, 0, 'L');
    $pdf->Cell(0, 4, 'Report ID: WQR-' . date('Ymd-His'), 0, 1, 'R');
    $pdf->SetXY(20, $pdf->GetY());
    $pdf->Cell(90, 4, 'Email: ' . $userEmail, 0, 0, 'L');
    $pdf->Cell(0, 4, 'Period: ' . $timeRange, 0, 1, 'R');
    $pdf->SetXY(20, $pdf->GetY());
    $pdf->Cell(0, 4, 'Export Time: ' . $exportTime, 0, 1, 'L');
    $pdf->SetY($pdf->GetY() + 8);
    
    // Table of Contents
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'TABLE OF CONTENTS', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $toc = [
        '1. Executive Summary',
        '2. Current Water Quality Status',
        '3. Statistical Analysis & Performance Metrics',
        '4. Quality Distribution Analysis',
        '5. Temporal Trends & Patterns',
        '6. Hourly Performance Analysis',
        '7. Daily Performance Summary',
        '8. Alert & Incident Analysis',
        '9. Compliance Assessment',
        '10. Recommendations & Actions',
        '11. Technical Specifications',
        '12. Appendices'
    ];
    
    foreach ($toc as $item) {
        $pdf->Cell(0, 4, $item, 0, 1, 'L');
    }
    $pdf->Ln(10);
    
    // 1. Executive Summary
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, '1. EXECUTIVE SUMMARY', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    $latestTurbidityQuality = getTurbidityQuality($latest['turbidity']);
    $latestPHQuality = getPHQuality($latest['ph']);
    $latestTempQuality = getTemperatureQuality($latest['temperature']);
    $latestTDSQuality = getTDSQuality($latest['tds']);
    
    $summary = "This comprehensive water quality analytics report provides detailed analysis of " . number_format($stats['total_readings']) . " measurements collected over the past " . $timeRange . ". ";
    $summary .= "The system demonstrates " . ($stats['avg_turbidity'] <= 2 ? "excellent" : ($stats['avg_turbidity'] <= 5 ? "good" : "concerning")) . " overall water quality with ";
    $summary .= "average turbidity of " . number_format($stats['avg_turbidity'], 2) . " NTU, pH of " . number_format($stats['avg_ph'], 2) . ", ";
    $summary .= "and temperature of " . number_format($stats['avg_temperature'], 1) . "°C.\n\n";
    
    $summary .= "Current Status Assessment:\n";
    $summary .= "• Turbidity: " . number_format($latest['turbidity'], 1) . " NTU (" . number_format(convertTurbidityToPercentage($latest['turbidity']), 1) . "%) - " . $latestTurbidityQuality['status'] . " - " . $latestTurbidityQuality['description'] . "\n";
    $summary .= "• TDS: " . number_format($latest['tds'], 0) . " ppm (" . number_format(convertTDSToPercentage($latest['tds']), 1) . "%) - " . $latestTDSQuality['status'] . " - " . $latestTDSQuality['description'] . "\n";
    $summary .= "• pH: " . number_format($latest['ph'], 2) . " - " . $latestPHQuality['status'] . " - " . $latestPHQuality['description'] . "\n";
    $summary .= "• Temperature: " . number_format($latest['temperature'], 1) . "°C - " . $latestTempQuality['status'] . " - " . $latestTempQuality['description'] . "\n\n";
    
    $summary .= "Key Findings:\n";
    $summary .= "• Data Collection: " . number_format($stats['total_readings']) . " total measurements analyzed\n";
    $summary .= "• System Reliability: " . number_format(($stats['total_readings'] / max($days, 1)), 1) . " average readings per day\n";
    $summary .= "• Quality Consistency: " . number_format(($qualityStats['good_turbidity'] / max($stats['total_readings'], 1)) * 100, 1) . "% of turbidity readings in good range\n";
    $summary .= "• Temperature Stability: " . number_format(($qualityStats['good_temp'] / max($stats['total_readings'], 1)) * 100, 1) . "% of temperature readings in optimal range\n";
    
    $pdf->MultiCell(0, 4, $summary, 0, 'L', false, 1);
    $pdf->Ln(8);
    
    // 2. Current Water Quality Status
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, '2. CURRENT WATER QUALITY STATUS', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    // Create status cards
    $statusData = [
        ['Parameter' => 'Turbidity', 'Value' => number_format($latest['turbidity'], 1) . ' NTU', 'Percentage' => number_format(convertTurbidityToPercentage($latest['turbidity']), 1) . '%', 'Quality' => $latestTurbidityQuality],
        ['Parameter' => 'TDS', 'Value' => number_format($latest['tds'], 0) . ' ppm', 'Percentage' => number_format(convertTDSToPercentage($latest['tds']), 1) . '%', 'Quality' => $latestTDSQuality],
        ['Parameter' => 'pH', 'Value' => number_format($latest['ph'], 2), 'Percentage' => 'N/A', 'Quality' => $latestPHQuality],
        ['Parameter' => 'Temperature', 'Value' => number_format($latest['temperature'], 1) . '°C', 'Percentage' => 'N/A', 'Quality' => $latestTempQuality]
    ];
    
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(40, 8, 'Parameter', 1, 0, 'C');
    $pdf->Cell(30, 8, 'Current Value', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Percentage', 1, 0, 'C');
    $pdf->Cell(30, 8, 'Quality Status', 1, 0, 'C');
    $pdf->Cell(0, 8, 'Assessment', 1, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 8);
    foreach ($statusData as $row) {
        $pdf->Cell(40, 6, $row['Parameter'], 1, 0, 'L');
        $pdf->Cell(30, 6, $row['Value'], 1, 0, 'C');
        $pdf->Cell(25, 6, $row['Percentage'], 1, 0, 'C');
        $pdf->Cell(30, 6, $row['Quality']['status'], 1, 0, 'C');
        $pdf->Cell(0, 6, substr($row['Quality']['description'], 0, 35) . '...', 1, 1, 'L');
    }
    $pdf->Ln(10);
    
    // 3. Statistical Analysis & Performance Metrics
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, '3. STATISTICAL ANALYSIS & PERFORMANCE METRICS', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    $metrics = "System Performance Overview:\n";
    $metrics .= "• Data Collection Period: " . $timeRange . " (" . $days . " days)\n";
    $metrics .= "• Total Measurements: " . number_format($stats['total_readings']) . " readings\n";
    $metrics .= "• Average Sampling Rate: " . number_format(($stats['total_readings'] / max($days, 1)), 1) . " readings per day\n";
    $metrics .= "• Data Availability: " . number_format(($stats['total_readings'] / ($days * 24)) * 100, 1) . "% coverage\n\n";
    
    $metrics .= "Central Tendency Analysis:\n";
    $metrics .= "• Turbidity: " . number_format($stats['avg_turbidity'], 2) . " NTU (avg), Range: " . number_format($stats['min_turbidity'], 2) . " - " . number_format($stats['max_turbidity'], 2) . " NTU\n";
    $metrics .= "• TDS: " . number_format($stats['avg_tds'], 0) . " ppm (avg), Range: " . number_format($stats['min_tds'], 0) . " - " . number_format($stats['max_tds'], 0) . " ppm\n";
    $metrics .= "• pH: " . number_format($stats['avg_ph'], 2) . " (avg), Range: " . number_format($stats['min_ph'], 2) . " - " . number_format($stats['max_ph'], 2) . "\n";
    $metrics .= "• Temperature: " . number_format($stats['avg_temperature'], 1) . "°C (avg), Range: " . number_format($stats['min_temp'], 1) . " - " . number_format($stats['max_temp'], 1) . "°C\n";
    
    $pdf->MultiCell(0, 4, $metrics, 0, 'L', false, 1);
    $pdf->Ln(8);
    
    // Detailed Statistical Table
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(30, 8, 'Parameter', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Minimum', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Maximum', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Average', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Range', 1, 0, 'C');
    $pdf->Cell(30, 8, 'Variation', 1, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 8);
    
    // Turbidity row
    $turbidityRange = $stats['max_turbidity'] - $stats['min_turbidity'];
    $turbidityVariation = calculateCoefficientOfVariation($stats['avg_turbidity'], $turbidityRange / 4);
    $pdf->Cell(30, 6, 'Turbidity (NTU)', 1, 0, 'L');
    $pdf->Cell(25, 6, number_format($stats['min_turbidity'], 2), 1, 0, 'C');
    $pdf->Cell(25, 6, number_format($stats['max_turbidity'], 2), 1, 0, 'C');
    $pdf->Cell(25, 6, number_format($stats['avg_turbidity'], 2), 1, 0, 'C');
    $pdf->Cell(25, 6, number_format($turbidityRange, 2), 1, 0, 'C');
    $pdf->Cell(30, 6, number_format($turbidityVariation, 1) . '%', 1, 1, 'C');
    
    // TDS row
    $tdsRange = $stats['max_tds'] - $stats['min_tds'];
    $tdsVariation = calculateCoefficientOfVariation($stats['avg_tds'], $tdsRange / 4);
    $pdf->Cell(30, 6, 'TDS (ppm)', 1, 0, 'L');
    $pdf->Cell(25, 6, number_format($stats['min_tds'], 0), 1, 0, 'C');
    $pdf->Cell(25, 6, number_format($stats['max_tds'], 0), 1, 0, 'C');
    $pdf->Cell(25, 6, number_format($stats['avg_tds'], 0), 1, 0, 'C');
    $pdf->Cell(25, 6, number_format($tdsRange, 0), 1, 0, 'C');
    $pdf->Cell(30, 6, number_format($tdsVariation, 1) . '%', 1, 1, 'C');
    
    // pH row
    $phRange = $stats['max_ph'] - $stats['min_ph'];
    $phVariation = calculateCoefficientOfVariation($stats['avg_ph'], $phRange / 4);
    $pdf->Cell(30, 6, 'pH', 1, 0, 'L');
    $pdf->Cell(25, 6, number_format($stats['min_ph'], 2), 1, 0, 'C');
    $pdf->Cell(25, 6, number_format($stats['max_ph'], 2), 1, 0, 'C');
    $pdf->Cell(25, 6, number_format($stats['avg_ph'], 2), 1, 0, 'C');
    $pdf->Cell(25, 6, number_format($phRange, 2), 1, 0, 'C');
    $pdf->Cell(30, 6, number_format($phVariation, 1) . '%', 1, 1, 'C');
    
    // Temperature row
    $tempRange = $stats['max_temp'] - $stats['min_temp'];
    $tempVariation = calculateCoefficientOfVariation($stats['avg_temperature'], $tempRange / 4);
    $pdf->Cell(30, 6, 'Temperature (°C)', 1, 0, 'L');
    $pdf->Cell(25, 6, number_format($stats['min_temp'], 1), 1, 0, 'C');
    $pdf->Cell(25, 6, number_format($stats['max_temp'], 1), 1, 0, 'C');
    $pdf->Cell(25, 6, number_format($stats['avg_temperature'], 1), 1, 0, 'C');
    $pdf->Cell(25, 6, number_format($tempRange, 1), 1, 0, 'C');
    $pdf->Cell(30, 6, number_format($tempVariation, 1) . '%', 1, 1, 'C');
    
    $pdf->Ln(8);
    
    // 4. Quality Distribution Analysis
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, '4. QUALITY DISTRIBUTION ANALYSIS', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    $distribution = "Quality Distribution Summary:\n\n";
    $distribution .= "Turbidity Quality Distribution:\n";
    $distribution .= "• Good (≤2 NTU): " . $qualityStats['good_turbidity'] . " readings (" . number_format(($qualityStats['good_turbidity'] / max($stats['total_readings'], 1)) * 100, 1) . "%)\n";
    $distribution .= "• Medium (2-5 NTU): " . $qualityStats['medium_turbidity'] . " readings (" . number_format(($qualityStats['medium_turbidity'] / max($stats['total_readings'], 1)) * 100, 1) . "%)\n";
    $distribution .= "• Critical (>5 NTU): " . $qualityStats['critical_turbidity'] . " readings (" . number_format(($qualityStats['critical_turbidity'] / max($stats['total_readings'], 1)) * 100, 1) . "%)\n\n";
    
    $distribution .= "pH Quality Distribution:\n";
    $distribution .= "• Good (6-8): " . $qualityStats['good_ph'] . " readings (" . number_format(($qualityStats['good_ph'] / max($stats['total_readings'], 1)) * 100, 1) . "%)\n";
    $distribution .= "• Medium (4-6, 8-10): " . $qualityStats['medium_ph'] . " readings (" . number_format(($qualityStats['medium_ph'] / max($stats['total_readings'], 1)) * 100, 1) . "%)\n";
    $distribution .= "• Critical (<4, >10): " . $qualityStats['critical_ph'] . " readings (" . number_format(($qualityStats['critical_ph'] / max($stats['total_readings'], 1)) * 100, 1) . "%)\n\n";
    
    $distribution .= "Temperature Distribution:\n";
    $distribution .= "• Good (20-30°C): " . $qualityStats['good_temp'] . " readings (" . number_format(($qualityStats['good_temp'] / max($stats['total_readings'], 1)) * 100, 1) . "%)\n";
    $distribution .= "• Cold (0-20°C): " . $qualityStats['cold_temp'] . " readings (" . number_format(($qualityStats['cold_temp'] / max($stats['total_readings'], 1)) * 100, 1) . "%)\n";
    $distribution .= "• Warm (30-40°C): " . $qualityStats['warm_temp'] . " readings (" . number_format(($qualityStats['warm_temp'] / max($stats['total_readings'], 1)) * 100, 1) . "%)\n";
    
    $pdf->MultiCell(0, 4, $distribution, 0, 'L', false, 1);
    $pdf->Ln(8);
    
    // 5. Temporal Trends & Patterns
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, '5. TEMPORAL TRENDS & PATTERNS', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    // Calculate trends
    $turbidityValues = array_column($hourlyData, 'turbidity');
    $tdsValues = array_column($hourlyData, 'tds');
    $phValues = array_column($hourlyData, 'ph');
    $tempValues = array_column($hourlyData, 'temperature');
    
    $turbidityTrend = getTrendDirection($turbidityValues);
    $tdsTrend = getTrendDirection($tdsValues);
    $phTrend = getTrendDirection($phValues);
    $tempTrend = getTrendDirection($tempValues);
    
    $trends = "Trend Analysis:\n";
    $trends .= "• Turbidity Trend: " . $turbidityTrend . " (average change over period)\n";
    $trends .= "• TDS Trend: " . $tdsTrend . " (average change over period)\n";
    $trends .= "• pH Trend: " . $phTrend . " (average change over period)\n";
    $trends .= "• Temperature Trend: " . $tempTrend . " (average change over period)\n\n";
    
    $trends .= "Pattern Recognition:\n";
    $trends .= "• Most Stable Parameter: " . (abs($phVariation) < abs($tempVariation) && abs($phVariation) < abs($turbidityVariation) && abs($phVariation) < abs($tdsVariation) ? "pH" : "Temperature") . "\n";
    $trends .= "• Most Variable Parameter: " . (abs($turbidityVariation) > abs($tempVariation) && abs($turbidityVariation) > abs($phVariation) && abs($turbidityVariation) > abs($tdsVariation) ? "Turbidity" : "TDS") . "\n";
    $trends .= "• Data Consistency: " . ($stats['total_readings'] > ($days * 20) ? "High" : ($stats['total_readings'] > ($days * 10) ? "Moderate" : "Low")) . " sampling frequency\n";
    
    $pdf->MultiCell(0, 4, $trends, 0, 'L', false, 1);
    $pdf->Ln(8);
    
    // 6. Hourly Performance Analysis
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, '6. HOURLY PERFORMANCE ANALYSIS', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    if (!empty($hourlyStatsData)) {
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(20, 6, 'Hour', 1, 0, 'C');
        $pdf->Cell(25, 6, 'Avg Turbidity', 1, 0, 'C');
        $pdf->Cell(25, 6, 'Avg TDS', 1, 0, 'C');
        $pdf->Cell(20, 6, 'Avg pH', 1, 0, 'C');
        $pdf->Cell(25, 6, 'Avg Temp', 1, 0, 'C');
        $pdf->Cell(20, 6, 'Readings', 1, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 7);
        foreach (array_slice($hourlyStatsData, 0, 12) as $hour) {
            $pdf->Cell(20, 5, $hour['hour'] . ':00', 1, 0, 'C');
            $pdf->Cell(25, 5, number_format($hour['avg_turbidity'], 1), 1, 0, 'C');
            $pdf->Cell(25, 5, number_format($hour['avg_tds'], 0), 1, 0, 'C');
            $pdf->Cell(20, 5, number_format($hour['avg_ph'], 2), 1, 0, 'C');
            $pdf->Cell(25, 5, number_format($hour['avg_temperature'], 1), 1, 0, 'C');
            $pdf->Cell(20, 5, $hour['readings'], 1, 1, 'C');
        }
        $pdf->Ln(8);
    }
    
    // 7. Daily Performance Summary
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, '7. DAILY PERFORMANCE SUMMARY', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    if (!empty($dailyData)) {
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(25, 6, 'Date', 1, 0, 'C');
        $pdf->Cell(25, 6, 'Avg Turbidity', 1, 0, 'C');
        $pdf->Cell(25, 6, 'Avg TDS', 1, 0, 'C');
        $pdf->Cell(20, 6, 'Avg pH', 1, 0, 'C');
        $pdf->Cell(25, 6, 'Avg Temp', 1, 0, 'C');
        $pdf->Cell(20, 6, 'Readings', 1, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 7);
        foreach (array_slice($dailyData, -10) as $day) {
            $pdf->Cell(25, 5, date('M j', strtotime($day['date'])), 1, 0, 'C');
            $pdf->Cell(25, 5, number_format($day['avg_turbidity'], 1), 1, 0, 'C');
            $pdf->Cell(25, 5, number_format($day['avg_tds'], 0), 1, 0, 'C');
            $pdf->Cell(20, 5, number_format($day['avg_ph'], 2), 1, 0, 'C');
            $pdf->Cell(25, 5, number_format($day['avg_temperature'], 1), 1, 0, 'C');
            $pdf->Cell(20, 5, $day['readings'], 1, 1, 'C');
        }
        $pdf->Ln(8);
    }
    
    // 8. Alert & Incident Analysis
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, '8. ALERT & INCIDENT ANALYSIS', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    $alertAnalysis = "Alert Summary:\n";
    if (!empty($alertsData)) {
        $totalAlerts = array_sum(array_column($alertsData, 'count_by_type'));
        $acknowledgedAlerts = 0;
        foreach ($alertsData as $alert) {
            $acknowledgedAlerts += $alert['acknowledged_alerts'] ?? 0;
        }
        $alertAnalysis .= "• Total Alerts: " . $totalAlerts . " incidents\n";
        $alertAnalysis .= "• Acknowledged Alerts: " . $acknowledgedAlerts . " (" . number_format(($acknowledgedAlerts / max($totalAlerts, 1)) * 100, 1) . "% response rate)\n";
        $alertAnalysis .= "• Outstanding Alerts: " . ($totalAlerts - $acknowledgedAlerts) . "\n\n";
        
        $alertAnalysis .= "Alert Breakdown by Type:\n";
        foreach ($alertsData as $alert) {
            $alertAnalysis .= "• " . ucfirst($alert['alert_type']) . ": " . $alert['count_by_type'] . " incidents\n";
        }
    } else {
        $alertAnalysis .= "• No alerts recorded during this period\n";
        $alertAnalysis .= "• System operating within normal parameters\n";
    }
    
    $pdf->MultiCell(0, 4, $alertAnalysis, 0, 'L', false, 1);
    $pdf->Ln(8);
    
    // 9. Compliance Assessment
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, '9. COMPLIANCE ASSESSMENT', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    $compliance = "Regulatory Compliance Summary:\n\n";
    $compliance .= "Turbidity Compliance:\n";
    $compliance .= "• Good Quality Readings: " . number_format(($qualityStats['good_turbidity'] / max($stats['total_readings'], 1)) * 100, 1) . "% (Target: >80%)\n";
    $compliance .= "• Critical Quality Readings: " . number_format(($qualityStats['critical_turbidity'] / max($stats['total_readings'], 1)) * 100, 1) . "% (Target: <5%)\n\n";
    
    $compliance .= "pH Compliance:\n";
    $compliance .= "• Optimal Range Readings: " . number_format(($qualityStats['good_ph'] / max($stats['total_readings'], 1)) * 100, 1) . "% (Target: >90%)\n";
    $compliance .= "• Critical Range Readings: " . number_format(($qualityStats['critical_ph'] / max($stats['total_readings'], 1)) * 100, 1) . "% (Target: <2%)\n\n";
    
    $compliance .= "Temperature Compliance:\n";
    $compliance .= "• Optimal Range Readings: " . number_format(($qualityStats['good_temp'] / max($stats['total_readings'], 1)) * 100, 1) . "% (Target: >70%)\n";
    $compliance .= "• System Stability: " . ($tempVariation < 20 ? "Excellent" : ($tempVariation < 40 ? "Good" : "Needs Improvement")) . "\n\n";
    
    $compliance .= "Overall Compliance Rating: " . (
        ($qualityStats['good_turbidity'] / max($stats['total_readings'], 1)) > 0.8 && 
        ($qualityStats['good_ph'] / max($stats['total_readings'], 1)) > 0.9 && 
        ($qualityStats['critical_turbidity'] / max($stats['total_readings'], 1)) < 0.05 ? 
        "COMPLIANT" : "NON-COMPLIANT"
    ) . "\n";
    
    $pdf->MultiCell(0, 4, $compliance, 0, 'L', false, 1);
    $pdf->Ln(8);
    
    // 10. Recommendations & Actions
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, '10. RECOMMENDATIONS & ACTIONS', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    $recommendations = "Priority Actions Required:\n\n";
    
    if (($qualityStats['critical_turbidity'] / max($stats['total_readings'], 1)) > 0.05) {
        $recommendations .= "HIGH PRIORITY - Turbidity Management:\n";
        $recommendations .= "• Investigate sources of high turbidity readings\n";
        $recommendations .= "• Implement additional filtration or treatment\n";
        $recommendations .= "• Increase monitoring frequency during high-risk periods\n\n";
    }
    
    if (($qualityStats['critical_ph'] / max($stats['total_readings'], 1)) > 0.02) {
        $recommendations .= "HIGH PRIORITY - pH Correction:\n";
        $recommendations .= "• Implement pH adjustment system\n";
        $recommendations .= "• Monitor pH more frequently\n";
        $recommendations .= "• Investigate source of pH fluctuations\n\n";
    }
    
    if ($tempVariation > 40) {
        $recommendations .= "MEDIUM PRIORITY - Temperature Control:\n";
        $recommendations .= "• Implement temperature stabilization measures\n";
        $recommendations .= "• Monitor environmental factors affecting temperature\n";
        $recommendations .= "• Consider thermal insulation or cooling systems\n\n";
    }
    
    $recommendations .= "General Recommendations:\n";
    $recommendations .= "• Maintain current monitoring schedule (" . number_format(($stats['total_readings'] / max($days, 1)), 1) . " readings/day)\n";
    $recommendations .= "• Continue data collection and analysis\n";
    $recommendations .= "• Review system performance monthly\n";
    $recommendations .= "• Document all maintenance activities\n";
    $recommendations .= "• Train personnel on water quality standards\n";
    
    $pdf->MultiCell(0, 4, $recommendations, 0, 'L', false, 1);
    $pdf->Ln(8);
    
    // 11. Technical Specifications
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, '11. TECHNICAL SPECIFICATIONS', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    $technical = "System Configuration:\n";
    $technical .= "• Monitoring System: Water Quality Monitoring Platform\n";
    $technical .= "• Data Collection Method: Automated sensor readings\n";
    $technical .= "• Sampling Frequency: Continuous monitoring\n";
    $technical .= "• Data Storage: MySQL database\n";
    $technical .= "• Report Generation: TCPDF library\n\n";
    
    $technical .= "Sensor Specifications:\n";
    $technical .= "• Turbidity Sensor: NTU measurement (0-3000 range)\n";
    $technical .= "• TDS Sensor: ppm measurement (0-1000 range)\n";
    $technical .= "• pH Sensor: Digital pH measurement (0-14 range)\n";
    $technical .= "• Temperature Sensor: °C measurement (-40 to 125°C)\n\n";
    
    $technical .= "Quality Standards Applied:\n";
    $technical .= "• Turbidity: WHO Guidelines (≤2 NTU optimal)\n";
    $technical .= "• pH: EPA Standards (6.5-8.5 optimal)\n";
    $technical .= "• TDS: WHO Guidelines (≤600 ppm acceptable)\n";
    $technical .= "• Temperature: Local environmental standards\n";
    
    $pdf->MultiCell(0, 4, $technical, 0, 'L', false, 1);
    $pdf->Ln(8);
    
    // 12. Appendices
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, '12. APPENDICES', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    $appendices = "Appendix A - Quality Assessment Guidelines:\n\n";
    $appendices .= "Turbidity Standards:\n";
    $appendices .= "• Good: ≤ 2 NTU (Clear water - excellent quality)\n";
    $appendices .= "• Medium: 2-5 NTU (Slightly cloudy - acceptable)\n";
    $appendices .= "• Critical: > 5 NTU (Very cloudy - requires attention)\n\n";
    
    $appendices .= "pH Standards:\n";
    $appendices .= "• Good: 6.0-8.0 (Optimal range for most applications)\n";
    $appendices .= "• Medium: 4.0-6.0 & 8.0-10.0 (Acceptable but may need adjustment)\n";
    $appendices .= "• Critical: < 4.0 or > 10.0 (Extreme values - immediate correction required)\n\n";
    
    $appendices .= "Temperature Standards:\n";
    $appendices .= "• Good: 20-30°C (Ideal temperature range)\n";
    $appendices .= "• Cold: 0-20°C (Cool water temperature)\n";
    $appendices .= "• Warm: 30-40°C (Warm water temperature)\n\n";
    
    $appendices .= "TDS Standards:\n";
    $appendices .= "• Good: ≤ 300 ppm (Low TDS - excellent water quality)\n";
    $appendices .= "• Medium: 300-600 ppm (Moderate TDS - acceptable levels)\n";
    $appendices .= "• High: > 600 ppm (High TDS - may affect taste and equipment)\n\n";
    
    $appendices .= "Appendix B - Report Metadata:\n";
    $appendices .= "• Report Generated: " . $exportTime . "\n";
    $appendices .= "• Generated By: " . $userName . " (" . $userEmail . ")\n";
    $appendices .= "• Report ID: WQR-" . date('Ymd-His') . "\n";
    $appendices .= "• Data Period: " . $timeRange . "\n";
    $appendices .= "• Total Data Points: " . number_format($stats['total_readings']) . " measurements\n";
    $appendices .= "• System Version: Water Quality Monitoring v1.0\n";
    $appendices .= "• Report Format: PDF (TCPDF)\n";
    
    $pdf->MultiCell(0, 4, $appendices, 0, 'L', false, 1);
    
    // Professional Footer
    $pdf->Ln(15);
    $pdf->SetFillColor(240, 248, 255);
    $pdf->Rect(15, $pdf->GetY(), 180, 20, 'F');
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetXY(20, $pdf->GetY() + 2);
    $pdf->Cell(0, 5, 'WATER QUALITY MONITORING SYSTEM', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetXY(20, $pdf->GetY());
    $pdf->Cell(0, 4, 'This comprehensive report was automatically generated by the Water Quality Monitoring System', 0, 1, 'C');
    $pdf->SetXY(20, $pdf->GetY());
    $pdf->Cell(0, 4, 'Generated on ' . $exportTime . ' by ' . $userName . ' | Report ID: WQR-' . date('Ymd-His'), 0, 1, 'C');
    $pdf->SetXY(20, $pdf->GetY());
    $pdf->Cell(0, 4, 'For technical support or questions about this report, please contact the system administrator.', 0, 1, 'C');
    
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Output PDF
    $pdf->Output($fileName, 'D');
}
?>