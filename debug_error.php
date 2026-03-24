<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Set up the request and user
$user = \App\Models\User::where('role', 'Program Manager')->first();
if (!$user) {
    die("No Program Manager user found\n");
}
Auth::login($user);

$request = Request::create('/api/program-regular/manager-program/budget-history', 'GET');

try {
    $controller = app(\App\Http\Controllers\Api\PrManagerProgramController::class);
    $response = $controller->getBudgetHistory($request);
    echo "SUCCESS:\n";
    echo $response->getContent();
} catch (\Throwable $e) {
    echo "ERROR CAUGHT:\n";
    echo $e->getMessage() . "\n";
    echo "FILE: " . $e->getFile() . "\n";
    echo "LINE: " . $e->getLine() . "\n";
    echo $e->getTraceAsString();
}
