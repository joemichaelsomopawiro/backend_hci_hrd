<?php
/**
 * Fix Excel Reader untuk File .xls
 * 
 * Script ini untuk memperbaiki masalah membaca file Excel .xls
 * yang menghasilkan error "Unable to identify a reader for this file"
 */

// Test file Excel
$testFile = '14 - 18 Juli 2025.xls';

echo "=== TEST EXCEL READER FIX ===\n\n";

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

// Test 2: Cek File
echo "\n2. Checking File...\n";
if (file_exists($testFile)) {
    echo "- File exists: ✅\n";
    echo "- File size: " . filesize($testFile) . " bytes\n";
    echo "- File extension: " . pathinfo($testFile, PATHINFO_EXTENSION) . "\n";
} else {
    echo "- File not found: ❌\n";
    exit;
}

// Test 3: Test dengan PhpSpreadsheet
echo "\n3. Testing PhpSpreadsheet...\n";

// Include PhpSpreadsheet
require_once 'vendor/autoload.php';

try {
    
    // Test dengan Xls Reader secara eksplisit
    echo "Testing with Xls Reader...\n";
    $reader = new Xls();
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($testFile);
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray();
    
    echo "✅ Success! File can be read.\n";
    echo "- Total rows: " . count($data) . "\n";
    echo "- Total columns: " . count($data[0]) . "\n";
    
    // Show first few rows
    echo "\nFirst 3 rows:\n";
    for ($i = 0; $i < min(3, count($data)); $i++) {
        echo "Row " . ($i + 1) . ": " . implode(" | ", array_slice($data[$i], 0, 5)) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Error type: " . get_class($e) . "\n";
}

// Test 4: Test dengan IOFactory
echo "\n4. Testing with IOFactory...\n";

try {
    $spreadsheet = IOFactory::load($testFile);
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray();
    
    echo "✅ Success with IOFactory!\n";
    
} catch (Exception $e) {
    echo "❌ Error with IOFactory: " . $e->getMessage() . "\n";
}

echo "\n=== TEST SELESAI ===\n"; 