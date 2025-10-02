<?php
// Simple test for analytics API
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Test basic query
    $testQuery = "SELECT COUNT(*) as count FROM water_readings";
    $testResult = $conn->query($testQuery);
    $count = $testResult->fetch_assoc()['count'];
    
    // Test TCPDF
    $pdf = new TCPDF();
    $pdf->AddPage();
    $pdf->Cell(0, 10, 'Test PDF', 0, 1);
    $pdfContent = $pdf->Output('test.pdf', 'S');
    
    echo json_encode([
        'success' => true,
        'message' => 'Analytics API is working',
        'database_readings' => $count,
        'tcpdf_working' => strlen($pdfContent) > 0,
        'user_id' => $_SESSION['user_id']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
