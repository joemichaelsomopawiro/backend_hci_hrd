<?php

/**
 * Test Sederhana untuk Sistem Upload Excel Attendance
 * 
 * Script ini untuk menguji sistem upload Excel secara lokal
 */

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

echo "=== TEST SISTEM UPLOAD EXCEL ATTENDANCE (LOCAL) ===\n\n";

// 1. Buat file Excel test
echo "1. Membuat file Excel test...\n";

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set header
$headers = [
    'No. ID',
    'Nama',
    'Tanggal',
    'Scan Masuk',
    'Scan Pulang',
    'Absent',
    'Jml Jam Kerja',
    'Jml Kehadiran'
];

foreach ($headers as $colIndex => $header) {
    $sheet->setCellValueByColumnAndRow($colIndex + 1, 1, $header);
}

// Set test data berdasarkan screenshot
$testData = [
    ['1', 'E.H Michael Palar', '14-Jul-25', '', '', 'True', '', ''],
    ['2', 'Budi Dharmadi', '14-Jul-25', '07:05', '16:40', 'False', '09:34', '09:34'],
    ['20111201', 'Steven Albert Reynold', '14-Jul-25', '09:10', '16:25', 'False', '07:15', '07:15'],
    ['20140202', 'Jelly Jeclien Lukas', '14-Jul-25', '14:12', '', 'False', '14:46', '14:46'],
    ['20140623', 'Jefri Siadari', '14-Jul-25', '11:30', '17:15', 'False', '17:28', '05:44'],
    ['20150101', 'Yola Yohana Tanara', '14-Jul-25', '11:18', '04:17', 'False', '16:58', '16:58'],
];

foreach ($testData as $rowIndex => $rowData) {
    foreach ($rowData as $colIndex => $value) {
        $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 2, $value);
    }
}

// Save test file
$writer = new Xlsx($spreadsheet);
$testFile = 'test_attendance_data.xlsx';
$writer->save($testFile);

echo "✓ File Excel test berhasil dibuat: {$testFile}\n";
echo "  Total rows: " . (count($testData) + 1) . " (including header)\n\n";

// 2. Test API endpoints
echo "2. Testing API endpoints...\n";

$baseUrl = 'http://localhost/backend_hci/public/api';

// Test validation rules
echo "  - Testing validation rules...\n";
$url = $baseUrl . '/attendance/upload-excel/validation-rules';
$response = makeRequest($url, 'GET');

if ($response && isset($response['success']) && $response['success']) {
    echo "    ✓ Validation rules berhasil diambil\n";
    echo "    Required columns: " . count($response['data']['required_columns']) . "\n";
} else {
    echo "    ✗ Gagal mengambil validation rules\n";
}

// Test template generation
echo "  - Testing template generation...\n";
$url = $baseUrl . '/attendance/upload-excel/template';
$response = makeRequest($url, 'GET');

if ($response && isset($response['success']) && $response['success']) {
    echo "    ✓ Template berhasil dibuat\n";
} else {
    echo "    ✗ Gagal membuat template\n";
}

// Test preview Excel
echo "  - Testing preview Excel...\n";
$url = $baseUrl . '/attendance/upload-excel/preview';
$response = makeRequest($url, 'POST', [
    'excel_file' => new CURLFile($testFile)
]);

if ($response && isset($response['success']) && $response['success']) {
    echo "    ✓ Preview berhasil\n";
    echo "    Total rows: " . $response['data']['total_rows'] . "\n";
    echo "    Preview rows: " . $response['data']['preview_rows'] . "\n";
    echo "    Errors: " . count($response['data']['errors']) . "\n";
    
    if (!empty($response['data']['preview_data'])) {
        echo "    Sample data:\n";
        foreach (array_slice($response['data']['preview_data'], 0, 2) as $index => $data) {
            echo "      Row " . ($index + 1) . ": " . $data['user_name'] . " - " . $data['date'] . " - " . $data['status'] . "\n";
        }
    }
} else {
    echo "    ✗ Gagal preview Excel\n";
    if ($response) {
        echo "    Error: " . ($response['message'] ?? 'Unknown error') . "\n";
    }
}

// Test upload Excel
echo "  - Testing upload Excel...\n";
$url = $baseUrl . '/attendance/upload-excel';
$response = makeRequest($url, 'POST', [
    'excel_file' => new CURLFile($testFile),
    'overwrite_existing' => false
]);

if ($response && isset($response['success']) && $response['success']) {
    echo "    ✓ Upload berhasil\n";
    echo "    Total rows: " . $response['data']['total_rows'] . "\n";
    echo "    Processed: " . $response['data']['processed'] . "\n";
    echo "    Created: " . $response['data']['created'] . "\n";
    echo "    Updated: " . $response['data']['updated'] . "\n";
    echo "    Skipped: " . $response['data']['skipped'] . "\n";
    echo "    Errors: " . count($response['data']['errors']) . "\n";
} else {
    echo "    ✗ Gagal upload Excel\n";
    if ($response) {
        echo "    Error: " . ($response['message'] ?? 'Unknown error') . "\n";
    }
}

echo "\n3. Testing attendance list...\n";

// Test get attendance list
$url = $baseUrl . '/attendance/list?date=2025-07-14&per_page=10';
$response = makeRequest($url, 'GET');

if ($response && isset($response['success']) && $response['success']) {
    echo "✓ Data attendance berhasil diambil dari database\n";
    echo "  Total records: " . $response['data']['total'] . "\n";
    echo "  Current page: " . $response['data']['current_page'] . "\n";
    echo "  Per page: " . $response['data']['per_page'] . "\n";
    
    if (!empty($response['data']['data'])) {
        echo "  Sample records:\n";
        foreach (array_slice($response['data']['data'], 0, 3) as $record) {
            echo "    - " . $record['user_name'] . " (" . $record['date'] . ") - " . $record['status'] . "\n";
        }
    }
} else {
    echo "✗ Gagal mengambil data attendance\n";
    if ($response) {
        echo "Error: " . ($response['message'] ?? 'Unknown error') . "\n";
    }
}

echo "\n=== SELESAI TESTING ===\n";
echo "File yang dibuat:\n";
echo "- test_attendance_data.xlsx (data test)\n";

/**
 * Make HTTP Request
 */
function makeRequest($url, $method = 'GET', $data = null)
{
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        echo "    CURL Error: " . $error . "\n";
        return false;
    }
    
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "    JSON Decode Error: " . json_last_error_msg() . "\n";
        echo "    Raw Response: " . substr($response, 0, 200) . "...\n";
        return false;
    }
    
    return $decoded;
} 