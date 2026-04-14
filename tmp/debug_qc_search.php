<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    Illuminate\Http\Request::create('/api/program-regular/database/programs', 'GET', [
        'q' => 'Verification', // Use a keyword that matches something known
        'year' => 'all',
        'status' => 'all',
        'sort' => 'air_date_desc'
    ])
);

// Mock Auth if needed, but for now let's just see if it runs or fails.
// Since it's a mock request in CLI, Auth::user() will be null.
// I'll need to bypass auth or mock a user.

// Better yet, let's just use the Controller directly in the script.
use App\Http\Controllers\Api\Pr\PrDatabaseController;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

$user = User::first(); // Just get some user
Auth::login($user);

$controller = new PrDatabaseController();
$request = new Illuminate\Http\Request([
    'q' => '', // Empty search (normal mode)
    'year' => 'all',
    'status' => 'all',
    'sort' => 'air_date_desc'
]);
$resNormal = $controller->getPrograms($request);

$requestSearch = new Illuminate\Http\Request([
    'q' => 'a', // Wide search (search mode)
    'year' => 'all',
    'status' => 'all',
    'sort' => 'air_date_desc'
]);
$resSearch = $controller->getPrograms($requestSearch);

echo "Normal Mode QC Count: " . count(json_decode($resNormal->getContent(), true)['data'][0]['qc'] ?? []) . "\n";
echo "Search Mode QC Count: " . count(json_decode($resSearch->getContent(), true)['data'][0]['qc'] ?? []) . "\n";

// Print first row of each to compare
echo "Normal Row QC: " . json_encode(json_decode($resNormal->getContent(), true)['data'][0]['qc'] ?? []) . "\n";
echo "Search Row QC: " . json_encode(json_decode($resSearch->getContent(), true)['data'][0]['qc'] ?? []) . "\n";
