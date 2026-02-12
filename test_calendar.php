<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\ProgramMusicScheduleController;
use Illuminate\Http\Request;

$controller = new ProgramMusicScheduleController();
$request = new Request();
$response = $controller->getCalendar($request);
$data = json_decode($response->getContent(), true);

echo "Unified Calendar Verification:\n";
echo "Success: " . ($data['success'] ? "True" : "False") . "\n";
echo "Total events: " . count($data['data']) . "\n\n";

if (!empty($data['data'])) {
    foreach (array_slice($data['data'], 0, 10) as $event) {
        printf("[%s] %s | Start: %s | Color: %s\n", 
            $event['type'], 
            $event['title'], 
            $event['start'], 
            $event['color'] ?? 'N/A'
        );
    }
}
