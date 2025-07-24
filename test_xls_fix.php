<?php
/**
 * Test Perbaikan File Excel .xls
 * 
 * File ini untuk memverifikasi bahwa perbaikan untuk membaca file .xls sudah berfungsi
 */

echo "=== TEST PERBAIKAN FILE EXCEL .XLS ===\n\n";

// Test 1: Cek PHP Extensions
echo "1. Checking PHP Extensions...\n";
$extensions = [
    'zip' => extension_loaded('zip'),
    'xml' => extension_loaded('xml'),
    'gd' => extension_loaded('gd'),
    'mbstring' => extension_loaded('mbstring')
];

foreach ($extensions as $ext => $loaded) {
    echo "- $ext: " . ($loaded ? "✅ Loaded" : "❌ Not Loaded") . "\n";
}

// Test 2: Cek File Excel
echo "\n2. Checking Excel File...\n";
$testFile = '14 - 18 Juli 2025.xls';

if (file_exists($testFile)) {
    echo "- File exists: ✅\n";
    echo "- File size: " . filesize($testFile) . " bytes\n";
    echo "- File extension: " . pathinfo($testFile, PATHINFO_EXTENSION) . "\n";
} else {
    echo "- File not found: ❌\n";
    echo "Please place the Excel file in the same directory as this script.\n";
    exit;
}

// Test 3: Test dengan PhpSpreadsheet
echo "\n3. Testing PhpSpreadsheet with .xls file...\n";

try {
    // Include PhpSpreadsheet
    require_once 'vendor/autoload.php';
    
    // Test dengan Xls Reader secara eksplisit
    echo "Testing with Xls Reader...\n";
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($testFile);
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray();
    
    echo "✅ Success! File .xls can be read.\n";
    echo "- Total rows: " . count($data) . "\n";
    echo "- Total columns: " . count($data[0]) . "\n";
    
    // Show header
    echo "\nHeader (Row 1):\n";
    echo implode(" | ", array_slice($data[0], 0, 8)) . "\n";
    
    // Show first few data rows
    echo "\nFirst 3 data rows:\n";
    for ($i = 1; $i < min(4, count($data)); $i++) {
        echo "Row " . ($i + 1) . ": " . implode(" | ", array_slice($data[$i], 0, 8)) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Error type: " . get_class($e) . "\n";
}

// Test 4: Test Service Method
echo "\n4. Testing Service Method...\n";

try {
    // Simulate file upload
    $uploadedFile = new \Illuminate\Http\UploadedFile(
        $testFile,
        '14 - 18 Juli 2025.xls',
        'application/vnd.ms-excel',
        null,
        true
    );
    
    // Test service
    $service = new \App\Services\AttendanceExcelUploadService();
    $result = $service->previewExcelData($uploadedFile);
    
    if ($result['success']) {
        echo "✅ Service method works! Preview successful.\n";
        echo "- Total rows: " . $result['data']['total_rows'] . "\n";
        echo "- Preview rows: " . $result['data']['preview_rows'] . "\n";
        echo "- Errors: " . count($result['data']['errors']) . "\n";
    } else {
        echo "❌ Service method failed: " . $result['message'] . "\n";
        if (isset($result['debug_info'])) {
            echo "Debug info: " . json_encode($result['debug_info'], JSON_PRETTY_PRINT) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Service test error: " . $e->getMessage() . "\n";
}

echo "\n=== TEST SELESAI ===\n";
echo "\nJika semua test berhasil, file Excel .xls sudah bisa dibaca!\n";
echo "Sekarang coba upload file Excel melalui frontend.\n"; 