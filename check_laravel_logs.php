<?php

// Check Laravel Logs untuk debugging
echo "=== CHECK LARAVEL LOGS ===\n";

$logPath = __DIR__ . '/storage/logs/laravel.log';

if (file_exists($logPath)) {
    echo "\n1. Checking Laravel log file...\n";
    echo "Log file exists: " . $logPath . "\n";
    echo "File size: " . filesize($logPath) . " bytes\n";
    echo "Last modified: " . date('Y-m-d H:i:s', filemtime($logPath)) . "\n";
    
    // Read last 50 lines
    $lines = file($logPath);
    $lastLines = array_slice($lines, -50);
    
    echo "\nLast 50 lines of log:\n";
    echo "=====================\n";
    foreach ($lastLines as $line) {
        echo $line;
    }
} else {
    echo "\n1. Laravel log file not found at: " . $logPath . "\n";
}

// Check if storage directory is writable
echo "\n2. Checking storage directory permissions...\n";
$storagePath = __DIR__ . '/storage';
if (is_dir($storagePath)) {
    echo "Storage directory exists\n";
    echo "Storage writable: " . (is_writable($storagePath) ? 'Yes' : 'No') . "\n";
} else {
    echo "Storage directory not found\n";
}

// Check logs subdirectory
$logsPath = __DIR__ . '/storage/logs';
if (is_dir($logsPath)) {
    echo "Logs directory exists\n";
    echo "Logs writable: " . (is_writable($logsPath) ? 'Yes' : 'No') . "\n";
    
    // List log files
    $logFiles = glob($logsPath . '/*.log');
    echo "Log files found: " . count($logFiles) . "\n";
    foreach ($logFiles as $file) {
        echo "- " . basename($file) . " (" . filesize($file) . " bytes)\n";
    }
} else {
    echo "Logs directory not found\n";
}

// Check .env file
echo "\n3. Checking environment configuration...\n";
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    echo "Environment file exists\n";
    
    // Check important settings
    $envContent = file_get_contents($envPath);
    
    $checks = [
        'APP_ENV' => 'Application Environment',
        'APP_DEBUG' => 'Debug Mode',
        'APP_URL' => 'Application URL',
        'DB_CONNECTION' => 'Database Connection',
        'DB_HOST' => 'Database Host',
        'DB_PORT' => 'Database Port',
        'DB_DATABASE' => 'Database Name',
        'CACHE_DRIVER' => 'Cache Driver',
        'SESSION_DRIVER' => 'Session Driver',
        'QUEUE_CONNECTION' => 'Queue Connection'
    ];
    
    foreach ($checks as $key => $description) {
        if (preg_match("/^{$key}=(.*)$/m", $envContent, $matches)) {
            echo "{$description}: {$matches[1]}\n";
        } else {
            echo "{$description}: Not set\n";
        }
    }
} else {
    echo "Environment file not found\n";
}

// Check if Laravel is properly configured
echo "\n4. Testing Laravel configuration...\n";
try {
    // Try to load Laravel
    require_once __DIR__ . '/vendor/autoload.php';
    $app = require_once __DIR__ . '/bootstrap/app.php';
    
    echo "Laravel autoloader loaded successfully\n";
    
    // Check if we can access config
    if (class_exists('Illuminate\Support\Facades\Config')) {
        echo "Laravel Config facade available\n";
    }
    
    // Check if we can access database
    if (class_exists('Illuminate\Support\Facades\DB')) {
        echo "Laravel DB facade available\n";
        
        // Try database connection
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
            echo "Database connection successful\n";
        } catch (Exception $e) {
            echo "Database connection failed: " . $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Laravel configuration error: " . $e->getMessage() . "\n";
}

// Check for common issues
echo "\n5. Checking for common issues...\n";

// Check if composer dependencies are installed
$vendorPath = __DIR__ . '/vendor';
if (is_dir($vendorPath)) {
    echo "Composer dependencies installed\n";
} else {
    echo "Composer dependencies NOT installed\n";
}

// Check if bootstrap/cache directory exists and is writable
$cachePath = __DIR__ . '/bootstrap/cache';
if (is_dir($cachePath)) {
    echo "Bootstrap cache directory exists\n";
    echo "Cache writable: " . (is_writable($cachePath) ? 'Yes' : 'No') . "\n";
} else {
    echo "Bootstrap cache directory not found\n";
}

// Check if .env.example exists
$envExamplePath = __DIR__ . '/.env.example';
if (file_exists($envExamplePath)) {
    echo "Environment example file exists\n";
} else {
    echo "Environment example file not found\n";
}

echo "\n=== CHECK COMPLETE ===\n"; 