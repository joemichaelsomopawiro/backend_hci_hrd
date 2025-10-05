<?php

$logFile = __DIR__ . '/storage/logs/laravel.log';

if (!file_exists($logFile)) {
    echo "❌ Log file tidak ditemukan\n";
    exit;
}

$lines = file($logFile);
$totalLines = count($lines);

echo "=== LATEST 100 LINES FROM LARAVEL LOG ===\n\n";

// Get last 100 lines
$lastLines = array_slice($lines, -100);

foreach ($lastLines as $line) {
    echo $line;
}

echo "\n\n=== END OF LOG ===\n";

