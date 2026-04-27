<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Http\Controllers\Api\ManagerProgramController;
use Illuminate\Http\Request;

$controller = new ManagerProgramController();
$request = new Request(['year' => '2026']);

// Login sebagai admin agar tidak terfilter (meskipun filter sudah dihapus)
$admin = \App\Models\User::where('role', 'Super Admin')->first();
auth()->login($admin);

$response = $controller->getMusicCalendarEvents($request);
$data = json_decode($response->getContent(), true);

echo "API Response for 2026:\n";
echo "Count: " . count($data['data'] ?? []) . "\n";
foreach ($data['data'] ?? [] as $event) {
    if ($event['date'] === '2026-04-24') {
        echo "FOUND EVENT: {$event['title']} | Date: {$event['date']} | Type: {$event['type']}\n";
    }
}

if (count($data['data']) > 0) {
    echo "\nAll Events Sample (First 3):\n";
    print_r(array_slice($data['data'], 0, 3));
}
