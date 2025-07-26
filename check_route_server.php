<?php
// Script untuk cek route di server
// Jalankan via SSH: php check_route_server.php

echo "=== CHECK ROUTE ON SERVER ===\n\n";

// Test route list
echo "🔍 Checking route list...\n";
$routeOutput = shell_exec('php artisan route:list 2>&1');
echo $routeOutput;

echo "\n=== CHECK SPECIFIC ROUTE ===\n";
$specificRoute = shell_exec('php artisan route:list | grep monthly-table 2>&1');
echo "Monthly table route:\n";
echo $specificRoute;

echo "\n=== CHECK ROUTE CACHE ===\n";
$routeCache = shell_exec('php artisan route:cache 2>&1');
echo $routeCache;

echo "\n=== SELESAI ===\n"; 