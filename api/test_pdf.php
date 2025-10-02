<?php
// Simple PDF test to verify TCPDF is working
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    ob_end_flush();
    exit;
}

try {
    require_once '../vendor/autoload.php';
    
    // Create a simple PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('Test');
    $pdf->SetTitle('PDF Test');
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'PDF Test - TCPDF is working correctly!', 0, 1, 'C');
    
    // Generate PDF content
    $pdfContent = $pdf->Output('test.pdf', 'S');
    
    // Clean output buffer
    ob_clean();
    
    // Set headers
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="test.pdf"');
    header('Content-Length: ' . strlen($pdfContent));
    
    // Output PDF
    echo $pdfContent;
    ob_end_flush();
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'PDF Test Failed: ' . $e->getMessage()]);
    ob_end_flush();
}
?>
