<?php
/**
 * Script untuk test sync bulanan
 */

// Load Laravel
require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\AttendanceController;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use Carbon\Carbon;

echo "🧪 TEST SYNC BULANAN\n";
echo "====================\n\n";

// Get current month info
$currentDate = Carbon::now();
$currentYear = $currentDate->year;
$currentMonth = $currentDate->month;
$monthName = $currentDate->format('F');

echo "📅 Bulan saat ini: {$monthName} {$currentYear}\n";
echo "📊 Rentang: " . $currentDate->startOfMonth()->format('Y-m-d') . " sampai " . $currentDate->endOfMonth()->format('Y-m-d') . "\n\n";

// Test via API
echo "🌐 Testing via API...\n";

$url = 'http://localhost:8000/api/attendance/sync-current-month';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 menit timeout
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";

if ($httpCode === 200) {
    $result = json_decode($response, true);
    
    if ($result['success']) {
        echo "✅ Sync bulanan berhasil!\n\n";
        echo "📊 Hasil Sync:\n";
        echo "   - Bulan: " . $result['data']['month'] . " " . $result['data']['year'] . "\n";
        echo "   - Total dari mesin: " . $result['data']['monthly_stats']['total_from_machine'] . "\n";
        echo "   - Filtered bulan ini: " . $result['data']['monthly_stats']['month_filtered'] . "\n";
        echo "   - Processed to logs: " . $result['data']['monthly_stats']['processed_to_logs'] . "\n";
        echo "   - Processed to attendances: " . $result['data']['monthly_stats']['processed_to_attendances'] . "\n";
        echo "   - Auto-sync users: " . $result['data']['auto_sync_result']['synced_count'] . "/" . $result['data']['auto_sync_result']['total_users'] . "\n";
        echo "   - Employee ID updates: " . $result['data']['employee_id_sync']['updated_count'] . "\n";
        
        echo "\n📅 Rentang tanggal:\n";
        echo "   - Start: " . $result['data']['monthly_stats']['start_date'] . "\n";
        echo "   - End: " . $result['data']['monthly_stats']['end_date'] . "\n";
        
    } else {
        echo "❌ Sync gagal: " . $result['message'] . "\n";
    }
} else {
    echo "❌ HTTP Error: " . $httpCode . "\n";
    echo "Response: " . $response . "\n";
}

echo "\n";

// Check database results
echo "📊 Checking database results...\n";

$startDate = $currentDate->startOfMonth()->format('Y-m-d');
$endDate = $currentDate->endOfMonth()->format('Y-m-d');

$totalLogs = AttendanceLog::count();
$totalAttendances = Attendance::count();
$monthLogs = AttendanceLog::whereBetween('datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])->count();
$monthAttendances = Attendance::whereBetween('date', [$startDate, $endDate])->count();

echo "📋 Database Summary:\n";
echo "   - Total logs (keseluruhan): {$totalLogs}\n";
echo "   - Total attendances (keseluruhan): {$totalAttendances}\n";
echo "   - Logs untuk {$monthName} {$currentYear}: {$monthLogs}\n";
echo "   - Attendances untuk {$monthName} {$currentYear}: {$monthAttendances}\n";

// Check unique users in this month
$uniqueUsers = Attendance::whereBetween('date', [$startDate, $endDate])
                        ->distinct()
                        ->pluck('user_pin')
                        ->count();

echo "👥 Unique users di {$monthName} {$currentYear}: {$uniqueUsers}\n";

echo "\n🎉 TEST SELESAI!\n";
echo "💡 Tips:\n";
echo "   - Gunakan script ini untuk test sync bulanan\n";
echo "   - Data akan selalu sesuai dengan bulan dan tahun saat ini\n";
echo "   - Tidak akan menarik data dari tahun lalu\n";
echo "   - Siap untuk export Excel bulanan\n"; 