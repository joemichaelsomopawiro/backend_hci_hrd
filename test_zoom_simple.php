<?php

require_once "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

// Test endpoint zoom attendance
$baseUrl = "https://api.hopemedia.id/api/ga";
$testData = [
    "employee_id" => 1,
    "zoom_link" => "https://zoom.us/j/test",
    "skip_time_validation" => true
];

echo "Testing Zoom Attendance...\n";
echo "URL: $baseUrl/zoom-join\n";
echo "Data: " . json_encode($testData) . "\n";

try {
    $response = Http::post($baseUrl . "/zoom-join", $testData);
    echo "Status: " . $response->status() . "\n";
    echo "Response: " . $response->body() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>