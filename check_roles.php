<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PrEpisodeCrew;

$crews = PrEpisodeCrew::select('role')->distinct()->get();
echo "ROLES:\n";
foreach ($crews as $crew) {
    echo "- " . $crew->role . "\n";
}
