<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Http\Controllers\Api\ManagerProgramController;
use Illuminate\Http\Request;

$controller = new ManagerProgramController();
$request = new Request(['year' => 2026]);
$response = $controller->getMusicCalendarEvents($request);
$data = json_decode($response->getContent(), true);

echo "CALENDAR EVENTS COUNT: " . count($data['data']) . "\n";
foreach ($data['data'] as $event) {
    if (strpos($event['title'], 'test') !== false || strpos($event['title'], 'Program Musik 1') !== false) {
        echo "FOUND REJECTED EVENT: " . $event['title'] . " on " . $event['date'] . "\n";
    }
}
echo "DONE\n";
