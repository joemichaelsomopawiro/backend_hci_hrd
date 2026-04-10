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
$request = new Illuminate\Http\Request([
    'q' => 'canva', // This was causing 500
    'year' => 'all',
    'status' => 'all',
    'sort' => 'air_date_desc'
]);

try {
    $res = $controller->getPrograms($request);
    echo "Status: " . $res->getStatusCode() . "\n";
    echo "Content Sample: " . substr($res->getContent(), 0, 100) . "...\n";
    echo "Verification SUCCESS!\n";
} catch (\Exception $e) {
    echo "Verification FAILED: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
