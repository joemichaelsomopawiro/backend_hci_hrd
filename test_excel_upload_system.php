<?php

/**
 * Test Script untuk Sistem Upload Excel Attendance
 * 
 * Script ini untuk menguji semua endpoint dan fitur upload Excel
 */

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AttendanceExcelUploadTest
{
    private $baseUrl = 'http://localhost/backend_hci/public/api';
    private $testData = [];

    public function __construct()
    {
        echo "=== TEST SISTEM UPLOAD EXCEL ATTENDANCE ===\n\n";
    }

    /**
     * Test 1: Generate Template Excel
     */
    public function testGenerateTemplate()
    {
        echo "1. Testing Generate Template Excel...\n";
        
        $url = $this->baseUrl . '/attendance/upload-excel/template';
        $response = $this->makeRequest($url, 'GET');
        
        if ($response['success']) {
            echo "✓ Template berhasil dibuat\n";
            echo "  Download URL: " . $response['data']['download_url'] . "\n";
            
            // Test download template
            $this->testDownloadTemplate();
        } else {
            echo "✗ Gagal membuat template: " . $response['message'] . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test 2: Download Template File
     */
    public function testDownloadTemplate()
    {
        echo "2. Testing Download Template File...\n";
        
        $url = $this->baseUrl . '/attendance/upload-excel/download-template';
        $response = $this->makeRequest($url, 'GET', null, true);
        
        if ($response !== false) {
            echo "✓ Template file berhasil didownload\n";
            echo "  File size: " . strlen($response) . " bytes\n";
            
            // Save template untuk testing
            file_put_contents('test_template.xlsx', $response);
            echo "  Template disimpan sebagai: test_template.xlsx\n";
        } else {
            echo "✗ Gagal download template file\n";
        }
        
        echo "\n";
    }

    /**
     * Test 3: Get Validation Rules
     */
    public function testGetValidationRules()
    {
        echo "3. Testing Get Validation Rules...\n";
        
        $url = $this->baseUrl . '/attendance/upload-excel/validation-rules';
        $response = $this->makeRequest($url, 'GET');
        
        if ($response['success']) {
            echo "✓ Validation rules berhasil diambil\n";
            echo "  Required columns: " . count($response['data']['required_columns']) . "\n";
            echo "  File requirements: " . $response['data']['file_requirements']['format'] . "\n";
            echo "  Max file size: " . $response['data']['file_requirements']['max_size'] . "\n";
        } else {
            echo "✗ Gagal mengambil validation rules: " . $response['message'] . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test 4: Create Test Excel File
     */
    public function testCreateTestExcel()
    {
        echo "4. Creating Test Excel File...\n";
        
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
        
        echo "✓ Test Excel file berhasil dibuat: {$testFile}\n";
        echo "  Total rows: " . (count($testData) + 1) . " (including header)\n";
        
        $this->testData = $testData;
        
        echo "\n";
    }

    /**
     * Test 5: Preview Excel Data
     */
    public function testPreviewExcel()
    {
        echo "5. Testing Preview Excel Data...\n";
        
        if (!file_exists('test_attendance_data.xlsx')) {
            echo "✗ Test file tidak ditemukan. Jalankan testCreateTestExcel() terlebih dahulu.\n\n";
            return;
        }
        
        $url = $this->baseUrl . '/attendance/upload-excel/preview';
        $response = $this->makeRequest($url, 'POST', [
            'excel_file' => new CURLFile('test_attendance_data.xlsx')
        ]);
        
        if ($response['success']) {
            echo "✓ Preview berhasil\n";
            echo "  Total rows: " . $response['data']['total_rows'] . "\n";
            echo "  Preview rows: " . $response['data']['preview_rows'] . "\n";
            echo "  Errors: " . count($response['data']['errors']) . "\n";
            
            if (!empty($response['data']['errors'])) {
                echo "  Error details:\n";
                foreach ($response['data']['errors'] as $error) {
                    echo "    - " . $error . "\n";
                }
            }
            
            // Show sample data
            if (!empty($response['data']['preview_data'])) {
                echo "  Sample data:\n";
                foreach (array_slice($response['data']['preview_data'], 0, 3) as $index => $data) {
                    echo "    Row " . ($index + 1) . ": " . $data['user_name'] . " - " . $data['date'] . " - " . $data['status'] . "\n";
                }
            }
        } else {
            echo "✗ Gagal preview Excel: " . $response['message'] . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test 6: Upload Excel (Without Overwrite)
     */
    public function testUploadExcel()
    {
        echo "6. Testing Upload Excel (Without Overwrite)...\n";
        
        if (!file_exists('test_attendance_data.xlsx')) {
            echo "✗ Test file tidak ditemukan. Jalankan testCreateTestExcel() terlebih dahulu.\n\n";
            return;
        }
        
        $url = $this->baseUrl . '/attendance/upload-excel';
        $response = $this->makeRequest($url, 'POST', [
            'excel_file' => new CURLFile('test_attendance_data.xlsx'),
            'overwrite_existing' => false
        ]);
        
        if ($response['success']) {
            echo "✓ Upload berhasil\n";
            echo "  Total rows: " . $response['data']['total_rows'] . "\n";
            echo "  Processed: " . $response['data']['processed'] . "\n";
            echo "  Created: " . $response['data']['created'] . "\n";
            echo "  Updated: " . $response['data']['updated'] . "\n";
            echo "  Skipped: " . $response['data']['skipped'] . "\n";
            echo "  Errors: " . count($response['data']['errors']) . "\n";
            
            if (!empty($response['data']['errors'])) {
                echo "  Error details:\n";
                foreach ($response['data']['errors'] as $error) {
                    echo "    - " . $error . "\n";
                }
            }
        } else {
            echo "✗ Gagal upload Excel: " . $response['message'] . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test 7: Upload Excel (With Overwrite)
     */
    public function testUploadExcelWithOverwrite()
    {
        echo "7. Testing Upload Excel (With Overwrite)...\n";
        
        if (!file_exists('test_attendance_data.xlsx')) {
            echo "✗ Test file tidak ditemukan. Jalankan testCreateTestExcel() terlebih dahulu.\n\n";
            return;
        }
        
        $url = $this->baseUrl . '/attendance/upload-excel';
        $response = $this->makeRequest($url, 'POST', [
            'excel_file' => new CURLFile('test_attendance_data.xlsx'),
            'overwrite_existing' => true
        ]);
        
        if ($response['success']) {
            echo "✓ Upload dengan overwrite berhasil\n";
            echo "  Total rows: " . $response['data']['total_rows'] . "\n";
            echo "  Processed: " . $response['data']['processed'] . "\n";
            echo "  Created: " . $response['data']['created'] . "\n";
            echo "  Updated: " . $response['data']['updated'] . "\n";
            echo "  Skipped: " . $response['data']['skipped'] . "\n";
            echo "  Errors: " . count($response['data']['errors']) . "\n";
        } else {
            echo "✗ Gagal upload Excel dengan overwrite: " . $response['message'] . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test 8: Upload Excel with Date Range
     */
    public function testUploadExcelWithDateRange()
    {
        echo "8. Testing Upload Excel with Date Range...\n";
        
        if (!file_exists('test_attendance_data.xlsx')) {
            echo "✗ Test file tidak ditemukan. Jalankan testCreateTestExcel() terlebih dahulu.\n\n";
            return;
        }
        
        $url = $this->baseUrl . '/attendance/upload-excel';
        $response = $this->makeRequest($url, 'POST', [
            'excel_file' => new CURLFile('test_attendance_data.xlsx'),
            'overwrite_existing' => false,
            'date_range_start' => '2025-07-14',
            'date_range_end' => '2025-07-14'
        ]);
        
        if ($response['success']) {
            echo "✓ Upload dengan date range berhasil\n";
            echo "  Total rows: " . $response['data']['total_rows'] . "\n";
            echo "  Processed: " . $response['data']['processed'] . "\n";
            echo "  Created: " . $response['data']['created'] . "\n";
            echo "  Updated: " . $response['data']['updated'] . "\n";
            echo "  Skipped: " . $response['data']['skipped'] . "\n";
        } else {
            echo "✗ Gagal upload Excel dengan date range: " . $response['message'] . "\n";
        }
        
        echo "\n";
    }

    /**
     * Test 9: Verify Data in Database
     */
    public function testVerifyDatabaseData()
    {
        echo "9. Testing Verify Database Data...\n";
        
        // Test get attendance list
        $url = $this->baseUrl . '/attendance/list?date=2025-07-14&per_page=10';
        $response = $this->makeRequest($url, 'GET');
        
        if ($response['success']) {
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
            echo "✗ Gagal mengambil data attendance: " . $response['message'] . "\n";
        }
        
        echo "\n";
    }

    /**
     * Run All Tests
     */
    public function runAllTests()
    {
        echo "Memulai semua test...\n\n";
        
        $this->testGenerateTemplate();
        $this->testGetValidationRules();
        $this->testCreateTestExcel();
        $this->testPreviewExcel();
        $this->testUploadExcel();
        $this->testUploadExcelWithOverwrite();
        $this->testUploadExcelWithDateRange();
        $this->testVerifyDatabaseData();
        
        echo "=== SELESAI TESTING ===\n";
        echo "File test yang dibuat:\n";
        echo "- test_template.xlsx (template download)\n";
        echo "- test_attendance_data.xlsx (data test)\n";
    }

    /**
     * Make HTTP Request
     */
    private function makeRequest($url, $method = 'GET', $data = null, $returnRaw = false)
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
            echo "CURL Error: " . $error . "\n";
            return false;
        }
        
        if ($returnRaw) {
            return $response;
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "JSON Decode Error: " . json_last_error_msg() . "\n";
            echo "Raw Response: " . substr($response, 0, 200) . "...\n";
            return false;
        }
        
        return $decoded;
    }
}

// Run tests
if (php_sapi_name() === 'cli') {
    $test = new AttendanceExcelUploadTest();
    $test->runAllTests();
} else {
    echo "Script ini harus dijalankan dari command line\n";
    echo "Usage: php test_excel_upload_system.php\n";
} 