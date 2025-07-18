<?php

// Setup SSL Certificate untuk API
echo "=== SETUP SSL CERTIFICATE FOR API ===\n";

// 1. Check current SSL status
echo "\n1. Checking current SSL status...\n";
$testUrls = [
    'https://api.hopechannel.id',
    'https://api.hopechannel.id/api',
    'https://api.hopechannel.id/api/auth/login'
];

foreach ($testUrls as $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "URL: " . $url . "\n";
    echo "HTTP Code: " . $httpCode . "\n";
    echo "Error: " . ($error ?: 'None') . "\n";
    echo "---\n";
}

// 2. Check SSL certificate
echo "\n2. Checking SSL certificate...\n";
$host = 'api.hopechannel.id';
$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'capture_peer_cert' => true,
    ]
]);

$result = @stream_socket_client("ssl://{$host}:443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
if ($result) {
    $cert = stream_context_get_params($result);
    if (isset($cert['options']['ssl']['peer_certificate'])) {
        echo "✅ SSL certificate exists\n";
        $certInfo = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
        echo "Subject: " . $certInfo['subject']['CN'] . "\n";
        echo "Valid until: " . date('Y-m-d H:i:s', $certInfo['validTo_time_t']) . "\n";
        echo "Issuer: " . $certInfo['issuer']['CN'] . "\n";
    }
    fclose($result);
} else {
    echo "❌ SSL certificate not found or invalid\n";
    echo "Error: $errstr ($errno)\n";
}

// 3. Generate .htaccess for SSL redirect
echo "\n3. Generating .htaccess for SSL redirect...\n";
$htaccessContent = '<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Force HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# Security Headers
<IfModule mod_headers.c>
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
</IfModule>';

$htaccessPath = __DIR__ . '/public/.htaccess';
if (file_put_contents($htaccessPath, $htaccessContent)) {
    echo "✅ .htaccess updated with SSL redirect\n";
} else {
    echo "❌ Failed to update .htaccess\n";
}

// 4. Update .env to use HTTPS
echo "\n4. Updating .env to use HTTPS...\n";
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    
    // Replace HTTP with HTTPS
    $newEnvContent = str_replace(
        'APP_URL=http://api.hopechannel.id',
        'APP_URL=https://api.hopechannel.id',
        $envContent
    );
    
    if (file_put_contents($envPath, $newEnvContent)) {
        echo "✅ .env updated to use HTTPS\n";
    } else {
        echo "❌ Failed to update .env\n";
    }
}

// 5. Clear Laravel cache
echo "\n5. Clearing Laravel cache...\n";
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

// 6. Test HTTPS endpoint
echo "\n6. Testing HTTPS endpoint...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.hopechannel.id/api/auth/login');
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
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTPS API Test:\n";
echo "HTTP Code: " . $httpCode . "\n";
echo "Error: " . ($error ?: 'None') . "\n";
echo "Response: " . $response . "\n";

if ($httpCode == 422 || $httpCode == 200) {
    echo "✅ HTTPS API is working\n";
} else {
    echo "❌ HTTPS API connection failed\n";
}

echo "\n=== SSL SETUP COMPLETE ===\n";
echo "\nNext steps:\n";
echo "1. Contact your hosting provider to enable SSL\n";
echo "2. Update frontend to use HTTPS\n";
echo "3. Test login again\n";
echo "4. If SSL not available, consider alternative solutions\n"; 