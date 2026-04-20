<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\BroadcastingWork;
use App\Http\Controllers\Api\ManagerBroadcastingController;
use Illuminate\Http\Request;

$dm = User::where('role', 'Distribution Manager')->first();
if (!$dm) {
    echo "❌ No DM found\n";
    exit;
}

Auth::login($dm);

$request = new Request([
    'status' => 'pending_approval,reviewing,pending,rejected,preparing,uploading,processing,scheduled,published,failed,cancelled'
]);

$controller = new ManagerBroadcastingController();
$response = $controller->getBroadcastingWorks($request);
$data = json_decode($response->getContent(), true);

echo "📡 API Response Check for Integrated View:\n";
echo "Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";

if (isset($data['data']['data'])) {
    $items = $data['data']['data'];
    echo "Count in ['data']['data']: " . count($items) . "\n";
    foreach ($items as $item) {
        echo "- ID: {$item['id']}, Status: {$item['status']}, Episode ID: {$item['episode_id']}\n";
    }
} else if (isset($data['data'])) {
    echo "Count in ['data']: " . count($data['data']) . "\n";
} else {
    echo "Unexpected structure:\n";
    print_r($data);
}
