<?php
use App\Models\PrEpisode;
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$eps = PrEpisode::with('program')->orderBy('id', 'desc')->limit(50)->get();
echo "START_EPS_LAST_50\n";
foreach ($eps as $e) {
    echo "ID:{$e->id}|Title:{$e->title}|Program:".($e->program->name ?? 'N/A')."|Status:{$e->status}\n";
}
echo "END_EPS_LAST_50\n";
