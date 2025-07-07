<?php
/**
 * Script untuk test export bulanan
 */

// Load Laravel
require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\AttendanceExportController;
use App\Models\Attendance;
use App\Models\EmployeeAttendance;
use Carbon\Carbon;

echo "🧪 TEST EXPORT BULANAN\n";
echo "======================\n\n";

// Test export untuk bulan Juli 2025
$controller = new AttendanceExportController();

// Buat request untuk export bulanan
$request = new \Illuminate\Http\Request();
$request->merge([
    'year' => 2025,
    'month' => 7,
    'format' => 'excel'
]);

echo "📊 Testing export bulanan Juli 2025...\n";

try {
    $response = $controller->exportMonthly($request);
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        echo "✅ Export berhasil!\n";
        echo "📁 File: " . $data['data']['filename'] . "\n";
        echo "🔗 URL: " . $data['data']['download_url'] . "\n";
        echo "👥 Total employees: " . $data['data']['total_employees'] . "\n";
        echo "📅 Working days: " . $data['data']['working_days'] . "\n";
    } else {
        echo "❌ Export gagal: " . $data['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test export CSV juga
echo "📊 Testing export CSV Juli 2025...\n";

$requestCSV = new \Illuminate\Http\Request();
$requestCSV->merge([
    'year' => 2025,
    'month' => 7,
    'format' => 'csv'
]);

try {
    $responseCSV = $controller->exportMonthly($requestCSV);
    $dataCSV = json_decode($responseCSV->getContent(), true);
    
    if ($dataCSV['success']) {
        echo "✅ Export CSV berhasil!\n";
        echo "📁 File: " . $dataCSV['data']['filename'] . "\n";
        echo "🔗 URL: " . $dataCSV['data']['download_url'] . "\n";
    } else {
        echo "❌ Export CSV gagal: " . $dataCSV['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error CSV: " . $e->getMessage() . "\n";
}

echo "\n✅ Test selesai!\n"; 