<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Episode;
use App\Models\PromotionWork;
use App\Models\User;

$user = User::where('name', 'Promotion Test')->first();
echo "User: " . ($user->name ?? 'Not Found') . " (ID: " . ($user->id ?? 'N/A') . ")\n";
echo "Role: " . ($user->role ?? 'N/A') . "\n\n";

$ep = Episode::where('episode_number', 19)->first();
if (!$ep) {
    echo "Episode 19 not found by episode_number 19, trying title...\n";
    $ep = Episode::where('title', 'like', '%Ep 19%')->first();
}

if ($ep) {
    echo "Episode 19 ID: " . $ep->id . "\n";
    echo "Air Date: " . ($ep->air_date ? $ep->air_date->toDateString() : 'NULL') . "\n";
    echo "Production Date: " . ($ep->production_date ? $ep->production_date->toDateString() : 'NULL') . "\n";
    
    $works = PromotionWork::where('episode_id', $ep->id)->get();
    echo "PromotionWorks found: " . $works->count() . "\n";
    foreach ($works as $w) {
        echo "- ID: {$w->id}, Type: {$w->work_type}, Status: {$w->status}, Shooting: ".($w->shooting_date ? $w->shooting_date->toDateString() : 'NULL').", CreatedBy: {$w->created_by}\n";
        echo "  File Links: " . json_encode($w->file_links) . "\n";
    }
} else {
    echo "Episode 19 still not found!\n";
}
