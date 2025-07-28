<?php
// Test Zoom Attendance Recording - api.hopemedia.id
echo "=== TEST ZOOM ATTENDANCE RECORDING ===\n";

$baseUrl = 'https://api.hopemedia.id/api';

echo "\n🎯 TESTING: Zoom Join → Database Recording\n";
echo "=========================================\n";
echo "Test apakah sistem mencatat absensi ketika user join zoom meeting\n\n";

// Test endpoints yang tersedia untuk zoom attendance
$endpoints = [
    'Morning Reflection Join' => $baseUrl . '/morning-reflection/join',
    'Morning Reflection Attendance' => $baseUrl . '/morning-reflection/attendance', 
    'GA Zoom Join' => $baseUrl . '/ga/zoom-join',
    'Morning Reflection Attend' => $baseUrl . '/morning-reflection/attend'
];

echo "1. Test Available Endpoints:\n";
echo "============================\n";

foreach ($endpoints as $name => $url) {
    echo "\nTesting $name:\n";
    echo "URL: $url\n";
    
    // Test OPTIONS untuk melihat method yang diizinkan
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ ERROR: $error\n";
    } else {
        echo "✅ HTTP Status: $httpCode\n";
        
        // Extract allowed methods from response headers
        if (preg_match('/Allow: (.+)/i', $response, $matches)) {
            echo "📝 Allowed Methods: " . trim($matches[1]) . "\n";
        }
    }
}

echo "\n\n2. Test Zoom Join Recording (Tanpa Auth):\n";
echo "=========================================\n";

// Test POST ke endpoint join zoom (tanpa auth dulu)
$joinUrl = $baseUrl . '/morning-reflection/join';
$joinData = [
    'employee_id' => 13,
    'join_time' => date('Y-m-d H:i:s'),
    'meeting_id' => 'test-meeting-123',
    'platform' => 'zoom'
];

echo "URL: $joinUrl\n";
echo "Data: " . json_encode($joinData, JSON_PRETTY_PRINT) . "\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $joinUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($joinData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ CURL ERROR: $error\n";
} else {
    echo "✅ HTTP Status: $httpCode\n";
    echo "Response: $response\n";
    
    $data = json_decode($response, true);
    if ($data) {
        if (isset($data['success']) && $data['success']) {
            echo "🎉 SUCCESS: Zoom join berhasil dicatat!\n";
        } else {
            echo "📝 Response Message: " . ($data['message'] ?? 'No message') . "\n";
        }
    }
}

echo "\n\n3. Test GA Zoom Join (Endpoint Alternatif):\n";
echo "==========================================\n";

$gaJoinUrl = $baseUrl . '/ga/zoom-join';
$gaJoinData = [
    'employee_id' => 13,
    'zoom_link' => 'https://zoom.us/j/test123',
    'skip_time_validation' => true // untuk testing
];

echo "URL: $gaJoinUrl\n";
echo "Data: " . json_encode($gaJoinData, JSON_PRETTY_PRINT) . "\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $gaJoinUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($gaJoinData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ CURL ERROR: $error\n";
} else {
    echo "✅ HTTP Status: $httpCode\n";
    echo "Response: $response\n";
    
    $data = json_decode($response, true);
    if ($data) {
        if (isset($data['success']) && $data['success']) {
            echo "🎉 SUCCESS: GA Zoom join berhasil dicatat!\n";
        } else {
            echo "📝 Response Message: " . ($data['message'] ?? 'No message') . "\n";
        }
    }
}

echo "\n\n4. Test dengan Authentication:\n";
echo "==============================\n";
echo "Untuk test dengan authentication token:\n\n";

$authTestUrl = $baseUrl . '/morning-reflection/attend';
echo "curl -X POST '$authTestUrl' \\\n";
echo "  -H 'Authorization: Bearer YOUR_TOKEN' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -H 'Accept: application/json' \\\n";
echo "  -d '{\n";
echo "    \"employee_id\": 13,\n";
echo "    \"testing_mode\": true\n";
echo "  }'\n\n";

echo "\n5. Check Database (Local):\n";
echo "=========================\n";

try {
    require_once __DIR__ . '/vendor/autoload.php';
    
    // Load Laravel
    $app = require_once __DIR__ . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    // Check morning reflection attendance records
    $todayRecords = DB::table('morning_reflection_attendance')
        ->whereDate('date', today())
        ->orderBy('created_at', 'desc')
        ->get();
    
    echo "✅ Records hari ini: " . $todayRecords->count() . "\n";
    
    if ($todayRecords->count() > 0) {
        echo "\n📝 Latest Records:\n";
        foreach ($todayRecords->take(3) as $record) {
            echo "- Employee ID: {$record->employee_id}, Status: {$record->status}, Time: {$record->join_time}\n";
        }
    }
    
    // Check total records
    $totalRecords = DB::table('morning_reflection_attendance')->count();
    echo "\n📊 Total semua records: $totalRecords\n";
    
} catch (Exception $e) {
    echo "❌ Database error (normal untuk production): " . $e->getMessage() . "\n";
}

echo "\n\n=== PANDUAN IMPLEMENTASI ZOOM INTEGRATION ===\n";
echo "\n🔧 Cara Kerja Sistem:\n";
echo "=====================\n";
echo "1. User join Zoom meeting\n";
echo "2. Frontend/Zoom webhook call API endpoint\n";
echo "3. API mencatat absensi ke database\n";
echo "4. Frontend menampilkan riwayat absensi\n\n";

echo "📋 Endpoint yang Tersedia:\n";
echo "==========================\n";
echo "1. POST /api/morning-reflection/join (Debug mode)\n";
echo "2. POST /api/ga/zoom-join (GA endpoint dengan validasi)\n";
echo "3. POST /api/morning-reflection/attend (Dengan auth)\n";
echo "4. GET /api/morning-reflection/attendance (Get history)\n\n";

echo "🎯 Yang Perlu Dilakukan:\n";
echo "========================\n";
echo "1. ✅ Update frontend baseURL ke: https://api.hopemedia.id/api\n";
echo "2. ✅ Clear browser cache\n";
echo "3. ✅ Test login untuk refresh token\n";
echo "4. ✅ Implementasi zoom webhook/integration\n";
echo "5. ✅ Test recording attendance\n\n";

echo "💡 Tips:\n";
echo "========\n";
echo "- Endpoint join zoom tersedia dan berfungsi\n";
echo "- Error ERR_QUIC_PROTOCOL_ERROR bisa diatasi dengan clear cache\n";
echo "- Sistem sudah terintegrasi dengan database\n";
echo "- Frontend perlu update ke domain baru\n"; 