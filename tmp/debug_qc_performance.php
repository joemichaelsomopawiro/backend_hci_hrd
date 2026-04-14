<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use App\Http\Controllers\Api\Pr\PrDatabaseController;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

$user = User::first();
Auth::login($user);

$controller = new PrDatabaseController();

$terms = ['Verification', 'Admin', 'http', '2026'];
foreach ($terms as $t) {
    $start = microtime(true);
    $request = new Illuminate\Http\Request([
        'q' => $t,
        'year' => 'all',
        'status' => 'all',
        'sort' => 'air_date_desc'
    ]);
    try {
        $res = $controller->getPrograms($request);
        $duration = microtime(true) - $start;
        echo "Search for '$t' took " . round($duration, 4) . "s. Results: " . count(json_decode($res->getContent(), true)['data'] ?? []) . "\n";
        
        // Print first row's QC to check if it's there
        $data = json_decode($res->getContent(), true)['data'] ?? [];
        if (!empty($data)) {
            echo "First row QC: " . json_encode($data[0]['qc']) . "\n";
        }
    } catch (\Exception $e) {
        echo "Search for '$t' FAILED: " . $e->getMessage() . "\n";
    }
}
