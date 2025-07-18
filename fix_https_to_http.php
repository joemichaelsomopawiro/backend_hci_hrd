<?php

// Fix HTTPS to HTTP Configuration
echo "=== FIX HTTPS TO HTTP CONFIGURATION ===\n";

// 1. Check current .env file
echo "\n1. Checking current .env configuration...\n";
$envPath = __DIR__ . '/.env';

if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    
    // Check current APP_URL
    if (preg_match('/APP_URL=(.*)/', $envContent, $matches)) {
        echo "Current APP_URL: " . $matches[1] . "\n";
        
        // If it's HTTPS, we need to change it to HTTP
        if (strpos($matches[1], 'https://') === 0) {
            echo "⚠️  Detected HTTPS URL, changing to HTTP...\n";
            
            // Replace HTTPS with HTTP
            $newEnvContent = str_replace(
                'APP_URL=https://api.hopechannel.id',
                'APP_URL=http://api.hopechannel.id',
                $envContent
            );
            
            // Also check for other HTTPS references
            $newEnvContent = str_replace(
                'https://api.hopechannel.id',
                'http://api.hopechannel.id',
                $newEnvContent
            );
            
            // Write back to .env file
            if (file_put_contents($envPath, $newEnvContent)) {
                echo "✅ Successfully updated .env file to use HTTP\n";
            } else {
                echo "❌ Failed to update .env file\n";
            }
        } else {
            echo "✅ APP_URL already uses HTTP\n";
        }
    } else {
        echo "❌ APP_URL not found in .env file\n";
    }
} else {
    echo "❌ .env file not found\n";
}

// 2. Test HTTP connection
echo "\n2. Testing HTTP connection...\n";
$testUrl = 'http://api.hopechannel.id/api/auth/login';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP URL: " . $testUrl . "\n";
echo "HTTP Code: " . $httpCode . "\n";
echo "Error: " . ($error ?: 'None') . "\n";

if ($httpCode == 200 || $httpCode == 404) {
    echo "✅ HTTP connection successful (404 is normal for HEAD request)\n";
} else {
    echo "❌ HTTP connection failed\n";
}

// 3. Test POST request with HTTP
echo "\n3. Testing POST request with HTTP...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'login' => 'test@example.com',
    'password' => 'password123'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Error: " . ($error ?: 'None') . "\n";
echo "Response: " . $response . "\n";

if ($httpCode == 422) {
    echo "✅ HTTP API is working (422 is expected for invalid credentials)\n";
} elseif ($httpCode == 200) {
    echo "✅ HTTP API is working\n";
} else {
    echo "❌ HTTP API connection failed\n";
}

// 4. Clear Laravel cache
echo "\n4. Clearing Laravel cache...\n";
$commands = [
    'php artisan config:clear',
    'php artisan cache:clear',
    'php artisan route:clear',
    'php artisan view:clear'
];

foreach ($commands as $command) {
    echo "Running: " . $command . "\n";
    $output = shell_exec($command);
    echo "Output: " . $output . "\n";
}

// 5. Check if server supports HTTP
echo "\n5. Checking server HTTP support...\n";
$host = 'api.hopechannel.id';
$connection = @fsockopen($host, 80, $errno, $errstr, 10);
if ($connection) {
    echo "✅ Port 80 (HTTP) is reachable\n";
    fclose($connection);
} else {
    echo "❌ Port 80 (HTTP) is not reachable: $errstr ($errno)\n";
}

// 6. Generate frontend configuration
echo "\n6. Frontend Configuration Update:\n";
echo "Update your frontend axios configuration to:\n\n";
echo "const api = axios.create({\n";
echo "    baseURL: 'http://api.hopechannel.id/api',\n";
echo "    timeout: 30000,\n";
echo "    headers: {\n";
echo "        'Content-Type': 'application/json',\n";
echo "        'Accept': 'application/json'\n";
echo "    }\n";
echo "});\n\n";

echo "=== FIX COMPLETE ===\n";
echo "\nNext steps:\n";
echo "1. Update your frontend to use HTTP instead of HTTPS\n";
echo "2. Clear browser cache\n";
echo "3. Test login again\n";
echo "4. If still having issues, check hosting provider for HTTP support\n"; 