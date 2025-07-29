<?php

require_once "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

use Illuminate\Support\Facades\Cache;

// Reset rate limit untuk IP tertentu
$ip = $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["REMOTE_ADDR"] ?? "127.0.0.1";
$key = "attendance_rate_limit_" . $ip;

Cache::forget($key);
echo "Rate limit reset untuk IP: $ip\n";
echo "Key yang direset: $key\n";

?>