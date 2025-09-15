<?php
// Test Production API api.hopemedia.id
echo "=== TEST PRODUCTION API - HOPEMEDIA.ID ===\n";

// Ambil domain dari .env file
$envPath = __DIR__ . '/.env';
$baseUrl = 'https://api.hopemedia.id/api'; // Default

if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    if (preg_match('/APP_URL=(.*)/', $envContent, $matches)) {
        $appUrl = trim($matches[1]);
        echo "APP_URL dari .env: $appUrl\n";
        $baseUrl = $appUrl . '/api';
    }
}

echo "Testing dengan base URL: $baseUrl\n\n";

// Test endpoints
$endpoints = [
    'Basic API' => $baseUrl,
    'Auth Login' => $baseUrl . '/auth/login',
    'Morning Reflection Attendance' => $baseUrl . '/morning-reflection/attendance',
    'Morning Reflection Status' => $baseUrl . '/morning-reflection/status'
];

echo "1. Testing Basic Connectivity:\n";
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
    curl_setopt($ch, CURLOPT_USERAGENT, 'Test Script');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ ERROR: $error\n";
    } else {
        echo "✅ HTTP Status: $httpCode\n";
        if ($httpCode == 200) {
            echo "✅ OK - Server responding normally\n";
        } elseif ($httpCode == 401) {
            echo "✅ OK - Server responding (needs authentication)\n";
        } elseif ($httpCode == 404) {
            echo "⚠️  404 - Endpoint not found\n";
        } elseif ($httpCode >= 500) {
            echo "❌ Server Error ($httpCode)\n";
        } else {
            echo "⚠️  Status: $httpCode\n";
        }
    }
}

echo "\n\n2. Test Morning Reflection dengan Sample Request:\n";
echo "===============================================\n";

// Test dengan parameter yang sama seperti di frontend
$attendanceUrl = $baseUrl . '/morning-reflection/attendance?employee_id=13&per_page=20&page=1&sort=date_desc';
echo "URL: $attendanceUrl\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $attendanceUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'User-Agent: Test Script'
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
    
    if ($httpCode == 401) {
        echo "✅ Server OK - Butuh Authentication (Normal untuk endpoint ini)\n";
        echo "💡 Error ini normal karena endpoint memerlukan Bearer token\n";
    } elseif ($httpCode == 200) {
        echo "✅ Request berhasil\n";
        $data = json_decode($response, true);
        if ($data && isset($data['success'])) {
            echo "Response success: " . ($data['success'] ? 'true' : 'false') . "\n";
            if (isset($data['message'])) {
                echo "Message: " . $data['message'] . "\n";
            }
        }
    } else {
        echo "Response preview: " . substr($response, 0, 300) . "\n";
    }
}

echo "\n\n3. Test dengan Valid Token (jika ada):\n";
echo "=====================================\n";

// Cari token valid dari database atau log
echo "Untuk test dengan token valid, Anda bisa:\n";
echo "1. Login melalui frontend untuk mendapat token\n";
echo "2. Copy token dari browser developer tools\n";
echo "3. Jalankan script dengan token:\n\n";

echo "curl -H \"Authorization: Bearer YOUR_TOKEN\" \\\n";
echo "     -H \"Content-Type: application/json\" \\\n";
echo "     -H \"Accept: application/json\" \\\n";
echo "     \"$attendanceUrl\"\n\n";

echo "\n4. Diagnosis Masalah Frontend:\n";
echo "=============================\n";

echo "Berdasarkan error di frontend:\n";
echo "- net::ERR_QUIC_PROTOCOL_ERROR\n";
echo "- Primary endpoint failed, fallback endpoint dimatikan\n\n";

echo "Kemungkinan penyebab:\n";
echo "1. ✅ HTTPS/HTTP Protocol Issue (sudah fixed dengan domain baru)\n";
echo "2. ⚠️  Network/QUIC Protocol Issue\n";
echo "3. ⚠️  Authentication Token expired/invalid\n";
echo "4. ⚠️  Server overload atau maintenance\n\n";

echo "Solusi yang perlu dicoba:\n";
echo "1. Update frontend baseURL ke: $baseUrl\n";
echo "2. Clear browser cache dan cookies\n";
echo "3. Check token authentication di frontend\n";
echo "4. Test dengan incognito mode\n";
echo "5. Check apakah ada rate limiting\n\n";

echo "\n5. Frontend Configuration Update:\n";
echo "=================================\n";
echo "const api = axios.create({\n";
echo "    baseURL: '$baseUrl',\n";
echo "    timeout: 30000,\n";
echo "    headers: {\n";
echo "        'Content-Type': 'application/json',\n";
echo "        'Accept': 'application/json'\n";
echo "    }\n";
echo "});\n\n";

echo "=== TEST SELESAI ===\n";
echo "\n💡 Next Steps:\n";
echo "1. Update frontend dengan baseURL baru\n";
echo "2. Clear browser cache\n";
echo "3. Test login ulang untuk refresh token\n";
echo "4. Monitor apakah error masih terjadi\n"; 