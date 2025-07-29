<?php

/**
 * Test Script untuk Attendance Detail API
 * 
 * Endpoint yang di-test:
 * 1. GET /api/attendance-detail/all - Semua data absensi dengan filter
 * 2. GET /api/attendance-detail/employee - Data absensi karyawan tertentu
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\AttendanceDetailController;

echo "=== TEST ATTENDANCE DETAIL API ===\n\n";

// Test 1: Get All Attendance Detail
echo "1. Testing GET /api/attendance-detail/all\n";
echo "==========================================\n";

$controller = new AttendanceDetailController();

// Test tanpa filter
echo "\n1.1 Test tanpa filter:\n";
$request = new Request();
$response = $controller->getAllAttendanceDetail($request);
$data = json_decode($response->getContent(), true);

if ($data['success']) {
    echo "✅ Success!\n";
    echo "Total records: " . $data['data']['total_records'] . "\n";
    echo "Statistics:\n";
    echo "  - Present: " . $data['data']['statistics']['present_count'] . "\n";
    echo "  - Absent: " . $data['data']['statistics']['absent_count'] . "\n";
    echo "  - Total work hours: " . $data['data']['statistics']['total_work_hours'] . "\n";
    
    if (!empty($data['data']['attendances'])) {
        echo "\nSample data:\n";
        $sample = $data['data']['attendances'][0];
        echo "  - Employee: " . $sample['employee_name'] . " (" . $sample['employee_position'] . ")\n";
        echo "  - Date: " . $sample['date'] . " (" . $sample['day_name'] . ")\n";
        echo "  - Check-in: " . ($sample['check_in'] ?? 'N/A') . "\n";
        echo "  - Check-out: " . ($sample['check_out'] ?? 'N/A') . "\n";
        echo "  - Status: " . $sample['status_label'] . "\n";
        echo "  - Work hours: " . ($sample['work_hours'] ?? 'N/A') . "\n";
    }
} else {
    echo "❌ Error: " . $data['message'] . "\n";
}

// Test dengan filter bulan
echo "\n1.2 Test dengan filter bulan (2025-01):\n";
$request = new Request(['month' => '2025-01']);
$response = $controller->getAllAttendanceDetail($request);
$data = json_decode($response->getContent(), true);

if ($data['success']) {
    echo "✅ Success!\n";
    echo "Total records: " . $data['data']['total_records'] . "\n";
    echo "Filtered by: " . $data['data']['filtered_by']['month'] . "\n";
} else {
    echo "❌ Error: " . $data['message'] . "\n";
}

// Test dengan pencarian
echo "\n1.3 Test dengan pencarian (Steven):\n";
$request = new Request(['search' => 'Steven']);
$response = $controller->getAllAttendanceDetail($request);
$data = json_decode($response->getContent(), true);

if ($data['success']) {
    echo "✅ Success!\n";
    echo "Total records: " . $data['data']['total_records'] . "\n";
    echo "Search query: " . $data['data']['filtered_by']['search'] . "\n";
} else {
    echo "❌ Error: " . $data['message'] . "\n";
}

// Test 2: Get Employee Attendance Detail
echo "\n\n2. Testing GET /api/attendance-detail/employee\n";
echo "==============================================\n";

// Test dengan employee_id yang valid
echo "\n2.1 Test dengan employee_id = 8:\n";
$request = new Request(['employee_id' => 8]);
$response = $controller->getEmployeeAttendanceDetail($request);
$data = json_decode($response->getContent(), true);

if ($data['success']) {
    echo "✅ Success!\n";
    echo "Employee: " . $data['data']['employee']['name'] . " (" . $data['data']['employee']['position'] . ")\n";
    echo "Total records: " . $data['data']['total_records'] . "\n";
    echo "Statistics:\n";
    echo "  - Present: " . $data['data']['statistics']['present_count'] . "\n";
    echo "  - Absent: " . $data['data']['statistics']['absent_count'] . "\n";
    echo "  - Total work hours: " . $data['data']['statistics']['total_work_hours'] . "\n";
    
    if (!empty($data['data']['attendances'])) {
        echo "\nSample attendance data:\n";
        $sample = $data['data']['attendances'][0];
        echo "  - Date: " . $sample['date'] . " (" . $sample['day_name'] . ")\n";
        echo "  - Check-in: " . ($sample['check_in'] ?? 'N/A') . "\n";
        echo "  - Check-out: " . ($sample['check_out'] ?? 'N/A') . "\n";
        echo "  - Status: " . $sample['status_label'] . "\n";
        echo "  - Work hours: " . ($sample['work_hours'] ?? 'N/A') . "\n";
        if ($sample['late_minutes'] > 0) {
            echo "  - Late: " . $sample['late_minutes'] . " minutes\n";
        }
        if ($sample['early_leave_minutes'] > 0) {
            echo "  - Early leave: " . $sample['early_leave_minutes'] . " minutes\n";
        }
    }
} else {
    echo "❌ Error: " . $data['message'] . "\n";
}

// Test dengan employee_id yang tidak valid
echo "\n2.2 Test dengan employee_id yang tidak valid (999):\n";
$request = new Request(['employee_id' => 999]);
$response = $controller->getEmployeeAttendanceDetail($request);
$data = json_decode($response->getContent(), true);

if (!$data['success']) {
    echo "✅ Expected error: " . $data['message'] . "\n";
} else {
    echo "❌ Unexpected success\n";
}

// Test tanpa employee_id
echo "\n2.3 Test tanpa employee_id:\n";
$request = new Request();
$response = $controller->getEmployeeAttendanceDetail($request);
$data = json_decode($response->getContent(), true);

if (!$data['success']) {
    echo "✅ Expected error: " . $data['message'] . "\n";
} else {
    echo "❌ Unexpected success\n";
}

echo "\n=== TEST SELESAI ===\n";

// Test dengan cURL (opsional)
echo "\n\n3. Testing dengan cURL (opsional)\n";
echo "==================================\n";

echo "Untuk test dengan cURL, gunakan command berikut:\n\n";

echo "Test semua data absensi:\n";
echo "curl -X GET 'http://localhost/api/attendance-detail/all' \\\n";
echo "  -H 'Authorization: Bearer YOUR_TOKEN' \\\n";
echo "  -H 'Accept: application/json'\n\n";

echo "Test data absensi dengan filter bulan:\n";
echo "curl -X GET 'http://localhost/api/attendance-detail/all?month=2025-01' \\\n";
echo "  -H 'Authorization: Bearer YOUR_TOKEN' \\\n";
echo "  -H 'Accept: application/json'\n\n";

echo "Test data absensi dengan pencarian:\n";
echo "curl -X GET 'http://localhost/api/attendance-detail/all?search=Steven' \\\n";
echo "  -H 'Authorization: Bearer YOUR_TOKEN' \\\n";
echo "  -H 'Accept: application/json'\n\n";

echo "Test data absensi karyawan tertentu:\n";
echo "curl -X GET 'http://localhost/api/attendance-detail/employee?employee_id=8' \\\n";
echo "  -H 'Authorization: Bearer YOUR_TOKEN' \\\n";
echo "  -H 'Accept: application/json'\n\n";

echo "Ganti YOUR_TOKEN dengan token autentikasi yang valid.\n"; 