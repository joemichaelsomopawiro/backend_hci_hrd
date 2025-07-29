<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

echo "=== DEBUG ZOOM ATTENDANCE PRODUCTION ISSUE ===\n";

// Cek environment
$environment = config('app.env');
echo "1. Current Environment: $environment\n";

// Cek timezone
$timezone = config('app.timezone');
$currentTime = Carbon::now();
echo "2. Timezone: $timezone\n";
echo "3. Current Time: " . $currentTime->format('Y-m-d H:i:s') . "\n";

// Cek jam operasional
$startTime = Carbon::today()->setTime(7, 10);
$endTime = Carbon::today()->setTime(8, 0);
echo "4. Morning Reflection Window: " . $startTime->format('H:i') . " - " . $endTime->format('H:i') . "\n";

$isInWindow = $currentTime->gte($startTime) && $currentTime->lte($endTime);
echo "5. Is Current Time in Window: " . ($isInWindow ? 'YES' : 'NO') . "\n";

if (!$isInWindow) {
    echo "   ⚠️  KEMUNGKINAN MASALAH: Waktu saat ini di luar jam operasional!\n";
    echo "   💡 SOLUSI: Gunakan parameter skip_time_validation=true untuk testing\n";
}

// Cek database connection
echo "\n=== DATABASE CONNECTION TEST ===\n";
try {
    DB::connection()->getPdo();
    echo "6. Database Connection: ✅ CONNECTED\n";
    
    // Test query ke tabel morning_reflection_attendance
    $count = DB::table('morning_reflection_attendance')->count();
    echo "7. Morning Reflection Records: $count\n";
    
    // Test query employees
    $empCount = DB::table('employees')->count();
    echo "8. Employees Count: $empCount\n";
    
} catch (Exception $e) {
    echo "6. Database Connection: ❌ FAILED\n";
    echo "   Error: " . $e->getMessage() . "\n";
}

// Cek rate limiting middleware
echo "\n=== RATE LIMITING TEST ===\n";
$rateLimitKey = 'attendance_rate_limit_' . request()->ip();
echo "9. Rate Limit Key: $rateLimitKey\n";

// Test endpoint dengan curl
echo "\n=== ENDPOINT TESTING ===\n";

$baseUrl = request()->getSchemeAndHttpHost() . '/api/ga';
echo "10. Base URL: $baseUrl\n";

// Test dengan employee_id 1
$testEmployeeId = 1;
echo "\n11. Testing POST /ga/zoom-join dengan employee_id: $testEmployeeId\n";

$postData = [
    'employee_id' => $testEmployeeId,
    'zoom_link' => 'https://zoom.us/j/test',
    'skip_time_validation' => true // Bypass time validation untuk testing
];

try {
    $response = Http::post($baseUrl . '/zoom-join', $postData);
    echo "    Status: " . $response->status() . "\n";
    echo "    Response: " . $response->body() . "\n";
    
    if ($response->successful()) {
        echo "    ✅ SUKSES: Data berhasil dikirim\n";
        
        // Cek apakah data masuk ke database
        $attendance = DB::table('morning_reflection_attendance')
            ->where('employee_id', $testEmployeeId)
            ->whereDate('date', Carbon::today())
            ->first();
            
        if ($attendance) {
            echo "    ✅ DATABASE: Data berhasil tersimpan\n";
            echo "    Data: " . json_encode($attendance) . "\n";
        } else {
            echo "    ❌ DATABASE: Data TIDAK tersimpan ke database\n";
        }
    } else {
        echo "    ❌ GAGAL: " . $response->body() . "\n";
    }
    
} catch (Exception $e) {
    echo "    ❌ ERROR: " . $e->getMessage() . "\n";
}

// Test tanpa skip_time_validation
echo "\n12. Testing POST /ga/zoom-join TANPA skip_time_validation\n";

$postDataNoSkip = [
    'employee_id' => $testEmployeeId,
    'zoom_link' => 'https://zoom.us/j/test'
];

try {
    $response = Http::post($baseUrl . '/zoom-join', $postDataNoSkip);
    echo "    Status: " . $response->status() . "\n";
    echo "    Response: " . $response->body() . "\n";
    
} catch (Exception $e) {
    echo "    ❌ ERROR: " . $e->getMessage() . "\n";
}

// Cek logs terbaru
echo "\n=== RECENT LOGS ===\n";
$logPath = storage_path('logs/laravel.log');
if (file_exists($logPath)) {
    $logs = file_get_contents($logPath);
    $recentLogs = array_slice(explode("\n", $logs), -20); // 20 baris terakhir
    echo "13. Recent Logs (last 20 lines):\n";
    foreach ($recentLogs as $log) {
        if (trim($log)) {
            echo "    " . $log . "\n";
        }
    }
} else {
    echo "13. Log file not found at: $logPath\n";
}

echo "\n=== TROUBLESHOOTING GUIDE ===\n";
echo "🔍 KEMUNGKINAN MASALAH:\n";
echo "1. Waktu akses di luar jam operasional (07:10-08:00)\n";
echo "2. Environment production memiliki validasi ketat\n";
echo "3. Rate limiting terlalu ketat\n";
echo "4. Database connection issue\n";
echo "5. Missing employee_id di tabel employees\n";

echo "\n💡 SOLUSI:\n";
echo "1. Pastikan akses dalam jam 07:10-08:00\n";
echo "2. Gunakan skip_time_validation=true untuk testing\n";
echo "3. Cek logs untuk error detail\n";
echo "4. Validasi employee_id exists di database\n";
echo "5. Test dengan script ini di production server\n";

echo "\n=== COMPLETED ===\n";
?> 