<?php
// Test Morning Reflection API dengan domain api.hopemedia
echo "=== TEST MORNING REFLECTION API - HOPEMEDIA ===\n";

$baseUrl = 'http://api.hopemedia/api';

// Test endpoints
$endpoints = [
    'Basic API' => $baseUrl,
    'Morning Reflection Attendance' => $baseUrl . '/morning-reflection/attendance',
    'Morning Reflection Status' => $baseUrl . '/morning-reflection/status',
    'Login Endpoint' => $baseUrl . '/auth/login'
];

echo "\n1. Testing Basic Connectivity:\n";
echo "==============================\n";

foreach ($endpoints as $name => $url) {
    echo "\nTesting $name:\n";
    echo "URL: $url\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ ERROR: $error\n";
    } else {
        echo "✅ HTTP Status: $httpCode\n";
        if ($httpCode == 200 || $httpCode == 401) {
            echo "✅ Server responding\n";
        } else {
            echo "⚠️  Unexpected status code\n";
        }
    }
}

echo "\n\n2. Testing Morning Reflection Attendance dengan Auth:\n";
echo "===================================================\n";

// Test dengan token sample (perlu auth)
$testToken = 'sample-token'; // Ganti dengan token yang valid
$attendanceUrl = $baseUrl . '/morning-reflection/attendance?employee_id=13&per_page=20&page=1&sort=date_desc';

echo "URL: $attendanceUrl\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $attendanceUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $testToken,
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
    echo "Response: " . substr($response, 0, 200) . "...\n";
    
    $data = json_decode($response, true);
    if ($data) {
        echo "JSON Response Valid: " . (isset($data['success']) ? ($data['success'] ? 'SUCCESS' : 'FAILED') : 'Unknown') . "\n";
        if (isset($data['message'])) {
            echo "Message: " . $data['message'] . "\n";
        }
    }
}

echo "\n\n3. Check Laravel Configuration:\n";
echo "==============================\n";

// Check .env file
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    
    // Check APP_URL
    if (preg_match('/APP_URL=(.*)/', $envContent, $matches)) {
        echo "Current APP_URL: " . trim($matches[1]) . "\n";
        
        if (strpos($matches[1], 'api.hopemedia') !== false) {
            echo "✅ APP_URL sudah menggunakan api.hopemedia\n";
        } else {
            echo "⚠️  APP_URL belum menggunakan api.hopemedia\n";
            echo "💡 Update .env dengan: APP_URL=http://api.hopemedia\n";
        }
    }
    
    // Check database config
    if (preg_match('/DB_HOST=(.*)/', $envContent, $matches)) {
        echo "DB_HOST: " . trim($matches[1]) . "\n";
    }
    
    if (preg_match('/DB_DATABASE=(.*)/', $envContent, $matches)) {
        echo "DB_DATABASE: " . trim($matches[1]) . "\n";
    }
} else {
    echo "❌ .env file not found\n";
}

echo "\n\n4. Test Database Connection:\n";
echo "============================\n";

try {
    require_once __DIR__ . '/vendor/autoload.php';
    
    // Load Laravel
    $app = require_once __DIR__ . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    // Test database
    $pdo = DB::connection()->getPdo();
    echo "✅ Database connection successful\n";
    
    // Test morning reflection table
    $count = DB::table('morning_reflection_attendance')->count();
    echo "✅ Morning reflection records: $count\n";
    
    // Test recent record
    $recent = DB::table('morning_reflection_attendance')
        ->orderBy('created_at', 'desc')
        ->first();
    
    if ($recent) {
        echo "✅ Latest record: Employee ID {$recent->employee_id}, Date {$recent->date}\n";
    } else {
        echo "⚠️  No records found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n\n5. Frontend Configuration Update:\n";
echo "=================================\n";
echo "Update frontend configuration dengan:\n\n";
echo "const api = axios.create({\n";
echo "    baseURL: 'http://api.hopemedia/api',\n";
echo "    timeout: 30000,\n";
echo "    headers: {\n";
echo "        'Content-Type': 'application/json',\n";
echo "        'Accept': 'application/json'\n";
echo "    }\n";
echo "});\n\n";

echo "=== TEST SELESAI ===\n";
echo "\n💡 Tips:\n";
echo "1. Pastikan APP_URL di .env sudah diupdate ke api.hopemedia\n";
echo "2. Jalankan: php artisan config:clear\n";
echo "3. Jalankan: php artisan cache:clear\n";
echo "4. Update frontend baseURL ke api.hopemedia\n";
echo "5. Pastikan token authentication valid\n"; 