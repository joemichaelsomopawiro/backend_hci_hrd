<?php
// Test GA Dashboard Worship Attendance Endpoint
echo "=== TEST GA DASHBOARD WORSHIP ATTENDANCE ===\n";

$baseUrl = 'https://api.hopemedia.id/api';
$token = '199|i5AvSYNatPgXabeKkhf2TLvy3gYkEyMRnq1xn7Tp80bef8ae'; // Token dari frontend

echo "\n1. Testing ALL Available Endpoints:\n";
echo "===================================\n";

$endpoints = [
    'GA Dashboard Main' => $baseUrl . '/ga-dashboard/worship-attendance',
    'GA Dashboard Today' => $baseUrl . '/ga-dashboard/worship-attendance?date=' . date('Y-m-d'),
    'GA Dashboard All' => $baseUrl . '/ga-dashboard/worship-attendance?all=true',
    'Morning Reflection Main' => $baseUrl . '/morning-reflection/attendance',
    'Morning Reflection Attendance' => $baseUrl . '/morning-reflection-attendance/attendance',
    'Legacy Endpoint' => $baseUrl . '/ga-dashboard/get-all-worship-attendance'
];

foreach ($endpoints as $name => $url) {
    echo "\nTesting $name:\n";
    echo "URL: $url\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: Test Script'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ CURL ERROR: $error\n";
    } else {
        echo "✅ HTTP Status: $httpCode\n";
        
        if ($httpCode == 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['success'])) {
                echo "🎉 SUCCESS: " . ($data['success'] ? 'Data loaded' : 'Failed') . "\n";
                if (isset($data['data'])) {
                    echo "📊 Records: " . count($data['data']) . "\n";
                }
                if (isset($data['message'])) {
                    echo "💬 Message: " . $data['message'] . "\n";
                }
            } else {
                echo "📝 Response (first 200 chars): " . substr($response, 0, 200) . "...\n";
            }
        } elseif ($httpCode == 404) {
            echo "❌ NOT FOUND - Endpoint tidak ada\n";
            // Show first part of 404 response
            if (strpos($response, '<!DOCTYPE html>') !== false) {
                echo "📄 Response: HTML 404 page (endpoint tidak ditemukan)\n";
            } else {
                echo "📝 Response: " . substr($response, 0, 200) . "...\n";
            }
        } elseif ($httpCode == 401) {
            echo "🔒 UNAUTHORIZED - Token tidak valid atau expired\n";
        } else {
            echo "⚠️  Status $httpCode: " . substr($response, 0, 200) . "...\n";
        }
    }
}

echo "\n\n2. Check Routes Registration:\n";
echo "============================\n";

try {
    require_once __DIR__ . '/vendor/autoload.php';
    
    // Load Laravel
    $app = require_once __DIR__ . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    // Get all registered routes
    $router = app('router');
    $routes = $router->getRoutes();
    
    $gaRoutes = [];
    foreach ($routes as $route) {
        $uri = $route->uri();
        if (strpos($uri, 'ga-dashboard') !== false) {
            $gaRoutes[] = [
                'method' => implode('|', $route->methods()),
                'uri' => $uri,
                'name' => $route->getName(),
                'action' => $route->getActionName()
            ];
        }
    }
    
    echo "Found " . count($gaRoutes) . " GA Dashboard routes:\n";
    foreach ($gaRoutes as $route) {
        echo "- {$route['method']} {$route['uri']} -> {$route['action']}\n";
    }
    
    // Check if specific route exists
    $worshipAttendanceExists = false;
    foreach ($gaRoutes as $route) {
        if ($route['uri'] === 'ga-dashboard/worship-attendance' && strpos($route['method'], 'GET') !== false) {
            $worshipAttendanceExists = true;
            echo "\n✅ Route 'ga-dashboard/worship-attendance' EXISTS!\n";
            echo "   Method: {$route['method']}\n";
            echo "   Action: {$route['action']}\n";
            break;
        }
    }
    
    if (!$worshipAttendanceExists) {
        echo "\n❌ Route 'ga-dashboard/worship-attendance' NOT FOUND!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error checking routes: " . $e->getMessage() . "\n";
}

echo "\n\n3. Check Controller Method:\n";
echo "==========================\n";

if (class_exists('\App\Http\Controllers\GaDashboardController')) {
    echo "✅ GaDashboardController class exists\n";
    
    $controller = new \App\Http\Controllers\GaDashboardController();
    if (method_exists($controller, 'getAllWorshipAttendance')) {
        echo "✅ getAllWorshipAttendance method exists\n";
    } else {
        echo "❌ getAllWorshipAttendance method NOT FOUND\n";
    }
} else {
    echo "❌ GaDashboardController class NOT FOUND\n";
}

echo "\n\n4. Check Frontend Configuration Issue:\n";
echo "=====================================\n";

// Test different base URLs that might be configured in frontend
$possibleBaseUrls = [
    'https://api.hopemedia.id/api',
    'http://api.hopemedia.id/api',
    'https://api.hopechannel.id/api',
    'http://api.hopechannel.id/api'
];

echo "Testing frontend base URL configurations:\n";
foreach ($possibleBaseUrls as $testBaseUrl) {
    $testUrl = $testBaseUrl . '/ga-dashboard/worship-attendance';
    echo "\nTesting: $testUrl\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request only
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ Connection failed: $error\n";
    } else {
        if ($httpCode == 200) {
            echo "✅ SUCCESS: Endpoint accessible\n";
        } elseif ($httpCode == 401) {
            echo "🔒 Endpoint exists but needs auth (OK)\n";
        } elseif ($httpCode == 404) {
            echo "❌ 404 Not Found\n";
        } else {
            echo "⚠️  HTTP $httpCode\n";
        }
    }
}

echo "\n\n5. Debug Token Validation:\n";
echo "=========================\n";

// Test with a simple endpoint first to verify token
$testUrl = $baseUrl . '/morning-reflection/status';
echo "Testing token with simple endpoint: $testUrl\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
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
    if ($httpCode == 200) {
        echo "🎉 Token is VALID and working\n";
    } elseif ($httpCode == 401) {
        echo "❌ Token is INVALID or expired\n";
    } else {
        echo "⚠️  Unexpected status: $httpCode\n";
    }
}

echo "\n\n=== DIAGNOSIS SUMMARY ===\n";
echo "\n🔍 KEMUNGKINAN PENYEBAB:\n";
echo "1. ❌ Route ga-dashboard/worship-attendance tidak terdaftar\n";
echo "2. ❌ Frontend menggunakan base URL yang salah\n";
echo "3. ❌ Token authentication expired/invalid\n";
echo "4. ❌ Middleware blocking request\n";
echo "5. ❌ Laravel cache route perlu di-clear\n\n";

echo "💡 SOLUSI:\n";
echo "1. Periksa route registration di routes/api.php\n";
echo "2. Clear Laravel route cache: php artisan route:clear\n";
echo "3. Verify frontend baseURL configuration\n";
echo "4. Check token expiration\n";
echo "5. Test dengan Postman/curl langsung\n\n";

echo "🔄 Next Steps:\n";
echo "- Jalankan: php artisan route:clear\n";
echo "- Jalankan: php artisan config:clear\n";
echo "- Check frontend axios baseURL configuration\n";
echo "- Verify authentication token\n"; 