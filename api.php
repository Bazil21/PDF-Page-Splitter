<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('fpdf186/fpdf.php');
require_once('FPDI-master/FPDI-master/src/autoload.php');

use setasign\Fpdi\Fpdi;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_FILES['pdf']) || !isset($_POST['filenames'])) {
        echo json_encode(['error' => 'Invalid request. Missing files or filenames.']);
        exit;
    }

    $pdfFile = $_FILES['pdf']['tmp_name'];
    $filenames = json_decode($_POST['filenames'], true);

    if (!file_exists($pdfFile)) {
        echo json_encode(['error' => 'No file uploaded.']);
        exit;
    }

    try {
        // Create source PDF instance
        $sourcePdf = new Fpdi();
        
        // Get page count from source PDF
        $pageCount = $sourcePdf->setSourceFile($pdfFile);

        if ($pageCount < count($filenames)) {
            echo json_encode(['error' => 'Number of filenames exceeds the pages in the PDF.']);
            exit;
        }

        // Prepare output folder
        $outputDir = 'split_pdfs/';
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0777, true)) {
                throw new Exception('Failed to create output directory');
            }
        }


           $savedFiles = [];
        $baseUrl = 'https://bazil.live/split_pdfs/'; // Base URL for file access

        // Process each filename
        foreach ($filenames as $i => $filename) {
            if (empty($filename)) continue;

            // Create new PDF for this page
            $newPdf = new Fpdi();  // Changed from FPDF to Fpdi
            
            // Set source file for the new instance
            $newPdf->setSourceFile($pdfFile);
            
            // Add page and import content
            $newPdf->AddPage();
            $templateId = $newPdf->importPage($i + 1);
            $newPdf->useTemplate($templateId);

            // Save the file
            $safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '', $filename);
            $outputFile = $outputDir . $safeFilename . '.pdf';
            $newPdf->Output('F', $outputFile);
            $savedFiles[] = $baseUrl . $safeFilename . '.pdf';
        }

        echo json_encode([
            'success' => true,
            'files' => $savedFiles,
            'message' => 'PDF split successfully',
            'pageCount' => $pageCount
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'error' => 'Error processing PDF: ' . $e->getMessage()
        ]);
    }
    exit;
}

echo json_encode(['error' => 'Invalid request method.']);
exit;