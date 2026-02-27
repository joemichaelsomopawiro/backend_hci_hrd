<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = \App\Models\User::where('name', 'Editor Test')->first() 
        ?? \App\Models\User::where('role', 'Editor')->first();

if (!$user) {
    echo "No Editor user found." . PHP_EOL;
    exit;
}

echo "Authenticated as: {$user->name} (ID: {$user->id})" . PHP_EOL;

// Mock authentication
Auth::login($user);

$controller = app(\App\Http\Controllers\Api\EditorController::class);

echo "\n--- API: index (works) ---" . PHP_EOL;
$request = new \Illuminate\Http\Request();
$response = $controller->index($request);
$data = json_decode($response->getContent(), true);

if (isset($data['data']['data'])) {
    foreach($data['data']['data'] as $work) {
        if ($work['id'] == 8) {
            echo "MATCH FOUND - ID: " . $work['id'] . PHP_EOL;
            echo "Status in Response: " . $work['status'] . PHP_EOL;
            echo "QC Feedback: " . ($work['qc_feedback'] ?? 'NONE') . PHP_EOL;
        }
    }
} else {
    echo "No works returned in expected format." . PHP_EOL;
    print_r($data);
}

echo "\n--- API: statistics ---" . PHP_EOL;
$responseStats = $controller->statistics($request);
echo "Stats Content: " . $responseStats->getContent() . PHP_EOL;
