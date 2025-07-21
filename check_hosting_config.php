<?php

// Check Hosting Configuration untuk debugging masalah login
echo "=== CHECK HOSTING CONFIGURATION ===\n";

// 1. Check PHP version and extensions
echo "\n1. PHP Configuration:\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";

// Check required extensions
$requiredExtensions = [
    'curl', 'json', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 
    'tokenizer', 'xml', 'ctype', 'bcmath', 'fileinfo'
];

echo "\nRequired Extensions:\n";
foreach ($requiredExtensions as $ext) {
    echo "- " . $ext . ": " . (extension_loaded($ext) ? 'Loaded' : 'NOT LOADED') . "\n";
}

// 2. Check server environment
echo "\n2. Server Environment:\n";
echo "REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'Unknown') . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'Unknown') . "\n";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'Unknown') . "\n";
echo "SERVER_PORT: " . ($_SERVER['SERVER_PORT'] ?? 'Unknown') . "\n";
echo "HTTPS: " . (isset($_SERVER['HTTPS']) ? 'Yes' : 'No') . "\n";

// 3. Check headers
echo "\n3. Request Headers:\n";
$headers = getallheaders();
if ($headers) {
    foreach ($headers as $name => $value) {
        echo "- " . $name . ": " . $value . "\n";
    }
} else {
    echo "No headers found\n";
}

// 4. Check if we can write to storage
echo "\n4. Storage Permissions:\n";
$storagePath = __DIR__ . '/storage';
$logsPath = $storagePath . '/logs';
$cachePath = __DIR__ . '/bootstrap/cache';

echo "Storage directory writable: " . (is_writable($storagePath) ? 'Yes' : 'No') . "\n";
echo "Logs directory writable: " . (is_writable($logsPath) ? 'Yes' : 'No') . "\n";
echo "Cache directory writable: " . (is_writable($cachePath) ? 'Yes' : 'No') . "\n";

// 5. Check .htaccess file
echo "\n5. .htaccess Configuration:\n";
$htaccessPath = __DIR__ . '/public/.htaccess';
if (file_exists($htaccessPath)) {
    echo ".htaccess file exists\n";
    $htaccessContent = file_get_contents($htaccessPath);
    echo "File size: " . strlen($htaccessContent) . " bytes\n";
    
    // Check for important directives
    if (strpos($htaccessContent, 'RewriteEngine On') !== false) {
        echo "✓ RewriteEngine is enabled\n";
    } else {
        echo "✗ RewriteEngine not found\n";
    }
    
    if (strpos($htaccessContent, 'RewriteCond') !== false) {
        echo "✓ Rewrite conditions found\n";
    } else {
        echo "✗ No rewrite conditions found\n";
    }
} else {
    echo ".htaccess file not found\n";
}

// 6. Check if Laravel is accessible
echo "\n6. Laravel Accessibility Test:\n";
try {
    // Test if we can load Laravel
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
        echo "✓ Composer autoloader loaded\n";
        
        if (file_exists(__DIR__ . '/bootstrap/app.php')) {
            $app = require_once __DIR__ . '/bootstrap/app.php';
            echo "✓ Laravel application loaded\n";
            
            // Test if we can access config
            if (class_exists('Illuminate\Support\Facades\Config')) {
                echo "✓ Config facade available\n";
            }
            
            // Test if we can access database
            if (class_exists('Illuminate\Support\Facades\DB')) {
                echo "✓ DB facade available\n";
                
                // Test database connection
                try {
                    \Illuminate\Support\Facades\DB::connection()->getPdo();
                    echo "✓ Database connection successful\n";
                } catch (Exception $e) {
                    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
                }
            }
        } else {
            echo "✗ bootstrap/app.php not found\n";
        }
    } else {
        echo "✗ Composer autoloader not found\n";
    }
} catch (Exception $e) {
    echo "✗ Laravel loading error: " . $e->getMessage() . "\n";
}

// 7. Check for common hosting issues
echo "\n7. Common Hosting Issues Check:\n";

// Check if mod_rewrite is enabled (Apache)
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "✓ mod_rewrite is enabled\n";
    } else {
        echo "✗ mod_rewrite is NOT enabled\n";
    }
} else {
    echo "? Cannot check mod_rewrite (not Apache or function not available)\n";
}

// Check if we can create files
$testFile = __DIR__ . '/test_write_permission.txt';
if (file_put_contents($testFile, 'test') !== false) {
    echo "✓ Can write files\n";
    unlink($testFile);
} else {
    echo "✗ Cannot write files\n";
}

// Check if we can read files
if (file_exists(__DIR__ . '/composer.json')) {
    echo "✓ Can read files\n";
} else {
    echo "✗ Cannot read files\n";
}

// 8. Check SSL/TLS configuration
echo "\n8. SSL/TLS Configuration:\n";
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    echo "✓ HTTPS is enabled\n";
} else {
    echo "✗ HTTPS is NOT enabled\n";
}

// Check SSL certificate
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
]);

$result = @stream_socket_client("ssl://{$host}:443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
if ($result) {
    echo "✓ SSL connection successful\n";
    fclose($result);
} else {
    echo "✗ SSL connection failed: $errstr ($errno)\n";
}

// 9. Check memory limits
echo "\n9. Memory and Performance:\n";
echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "Max execution time: " . ini_get('max_execution_time') . " seconds\n";
echo "Upload max filesize: " . ini_get('upload_max_filesize') . "\n";
echo "Post max size: " . ini_get('post_max_size') . "\n";

// 10. Check if we're in a subdirectory
echo "\n10. Directory Structure:\n";
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$currentPath = __DIR__;

echo "Script path: " . $scriptPath . "\n";
echo "Document root: " . $documentRoot . "\n";
echo "Current path: " . $currentPath . "\n";

if (strpos($currentPath, $documentRoot) === 0) {
    $relativePath = substr($currentPath, strlen($documentRoot));
    echo "Relative to document root: " . $relativePath . "\n";
} else {
    echo "Not in document root\n";
}

echo "\n=== HOSTING CHECK COMPLETE ===\n"; 