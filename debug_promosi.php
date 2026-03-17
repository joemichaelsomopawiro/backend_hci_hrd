<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PrEditorPromosiWork;

$works = PrEditorPromosiWork::with(['editorWork', 'promotionWork', 'episode'])
    ->orderBy('updated_at', 'desc')
    ->take(10)
    ->get();

echo str_pad("ID", 5) . " | " . str_pad("Status", 15) . " | " . str_pad("Ed ID", 6) . " | " . str_pad("Ed S", 15) . " | " . str_pad("Pr S", 15) . " | " . "Episode\n";
echo str_repeat("-", 80) . "\n";

foreach ($works as $w) {
    echo str_pad($w->id, 5) . " | " 
       . str_pad($w->status, 15) . " | " 
       . str_pad($w->pr_editor_work_id ?? 'N/A', 6) . " | " 
       . str_pad($w->editorWork->status ?? 'N/A', 15) . " | " 
       . str_pad($w->promotionWork->status ?? 'N/A', 15) . " | " 
       . ($w->episode->episode_title ?? 'N/A') . "\n";
}
