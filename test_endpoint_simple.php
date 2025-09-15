<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\PersonalAttendanceController;
use Illuminate\Http\Request;

echo "=== Testing PersonalAttendanceController ===\n";

// Create a mock request
$request = new Request();
$request->merge(['employee_id' => 8]);

// Create controller instance
$controller = new PersonalAttendanceController();

try {
    // Call the method
    $response = $controller->getPersonalOfficeAttendance($request);
    
    echo "Response Status: " . $response->getStatusCode() . "\n";
    echo "Response Content:\n";
    echo json_encode(json_decode($response->getContent()), JSON_PRETTY_PRINT) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "=== Test Complete ===\n"; 