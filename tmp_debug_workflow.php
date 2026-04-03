<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use App\Http\Controllers\Api\EpisodeController;
use Illuminate\Http\Request;

$controller = app(EpisodeController::class);
$request = Request::create('/api/episodes/3221/monitor-workflow', 'GET');
$response = $controller->monitorWorkflow(3221);

$data = json_decode($response->getContent(), true);
echo json_encode($data['data']['workflow_steps'], JSON_PRETTY_PRINT);
echo "\n";
