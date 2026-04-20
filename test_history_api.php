<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BroadcastingWork;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ManagerBroadcastingController;

// Simulate a Distribution Manager user
$user = User::where('role', 'Distribution Manager')->first();
if (!$user) {
    echo "❌ No Distribution Manager found!\n";
    exit;
}

echo "🔍 Testing getBroadcastingWorks with history filter for User: {$user->name} (Role: {$user->role})\n";

Auth::login($user);

$request = new Request([
    'status' => 'pending,rejected,preparing,uploading,processing,scheduled,published,failed,cancelled'
]);

$controller = new ManagerBroadcastingController();
$response = $controller->getBroadcastingWorks($request);

echo "📡 API Response Status: " . $response->getStatusCode() . "\n";
$data = json_decode($response->getContent(), true);

if (isset($data['data']['data'])) {
    $items = $data['data']['data'];
    echo "📊 Total items found: " . count($items) . "\n";
    foreach ($items as $item) {
        echo "- ID: {$item['id']}, Status: {$item['status']}, Display Status: {$item['display_status']}\n";
    }
} else {
    echo "❌ No items found in response or unexpected format.\n";
    print_r($data);
}
