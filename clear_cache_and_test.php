<?php
// Script untuk clear cache Laravel dan test route
// Jalankan via SSH: php clear_cache_and_test.php

echo "=== CLEAR CACHE LARAVEL ===\n\n";

// Clear cache commands
$commands = [
    'php artisan config:clear',
    'php artisan route:clear', 
    'php artisan cache:clear',
    'php artisan view:clear'
];

foreach ($commands as $command) {
    echo "🔄 Running: $command\n";
    $output = shell_exec($command . ' 2>&1');
    echo "Output: " . trim($output) . "\n\n";
}

echo "✅ Cache cleared successfully!\n\n";

// Test route list
echo "=== TEST ROUTE LIST ===\n";
$routeOutput = shell_exec('php artisan route:list --name=monthly-table 2>&1');
echo $routeOutput;

echo "\n=== SELESAI ===\n"; 