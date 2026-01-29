<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Checking PrCreativeWork...\n";
    $work = App\Models\PrCreativeWork::first();
    echo "PrCreativeWork OK: " . ($work ? $work->id : 'none') . "\n";

    echo "Checking PrEpisode...\n";
    $ep = App\Models\PrEpisode::first();
    echo "PrEpisode OK: " . ($ep ? $ep->id : 'none') . "\n";

    echo "Checking Notification...\n";
    $notif = App\Models\Notification::first();
    echo "Notification OK: " . ($notif ? $notif->id : 'none') . "\n";

    echo "Instantiating PrCreativeController...\n";
    $controller = new App\Http\Controllers\Api\Pr\PrCreativeController();
    echo "Controller Instantiated OK\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
