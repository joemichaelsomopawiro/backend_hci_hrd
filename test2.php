<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::find(66);
\Illuminate\Support\Facades\Auth::login($user);

$request = \Illuminate\Http\Request::create('/api/program-regular/manager-program/budget-history', 'GET');
$controller = new \App\Http\Controllers\Api\PrManagerProgramController(
    app(\App\Services\PrProgramService::class),
    app(\App\Services\PrConceptService::class),
    app(\App\Services\PrActivityLogService::class)
);

try {
    $response = $controller->getBudgetHistory($request);
    echo $response->getContent();
} catch (\Exception $e) {
    echo "EXCEPTION CAUGHT:\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString();
} catch (\Error $e) {
    echo "FATAL ERROR CAUGHT:\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
