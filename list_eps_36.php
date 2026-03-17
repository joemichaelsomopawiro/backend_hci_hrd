<?php
use App\Models\PrEpisode;
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$eps = PrEpisode::where('pr_program_id', 36)->get();
echo "START_EPS_LIST_36\n";
foreach ($eps as $e) {
    echo "ID:{$e->id}|Title:{$e->title}|Status:{$e->status}\n";
}
echo "END_EPS_LIST_36\n";
