<?php
require __DIR__ . '/vendor/autoload.php';
chdir(__DIR__);
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

ob_start();

echo "=== SYSTEM ACTIVITY SNAPSHOT ===\n\n";


echo "--- Recent Episodes (Top 5) ---\n";
$eps = \App\Models\PrEpisode::latest()->take(5)->get();
foreach ($eps as $ep) {
    echo "ID: {$ep->id}, Num: {$ep->episode_number}, Created: {$ep->created_at->format('Y-m-d H:i')}\n";
}

echo "\n--- Recent Creative Works (Top 5) ---\n";
$cws = \App\Models\PrCreativeWork::with('episode')->latest()->take(5)->get();
foreach ($cws as $cw) {
    $epNum = $cw->episode ? $cw->episode->episode_number : 'NULL';
    echo "CW ID: {$cw->id}, Ep ID: {$cw->pr_episode_id} (Num: {$epNum}), Status: {$cw->status}, Created: {$cw->created_at->format('Y-m-d H:i')}, ScriptOk: {$cw->script_approved}, BudgetOk: {$cw->budget_approved}\n";
}

echo "\n--- Recent Production Works (Top 5) ---\n";
$pws = \App\Models\PrProduksiWork::latest()->take(5)->get();
foreach ($pws as $pw) {
    echo "PW ID: {$pw->id}, Ep ID: {$pw->pr_episode_id}, Status: {$pw->status}, Created: {$pw->created_at->format('Y-m-d H:i')}\n";
}

echo "\n--- Recent Promotion Works (Top 5) ---\n";
$prws = \App\Models\PrPromotionWork::latest()->take(5)->get();
foreach ($prws as $prw) {
    echo "PRW ID: {$prw->id}, Ep ID: {$prw->pr_episode_id}, Status: {$prw->status}, Created: {$prw->created_at->format('Y-m-d H:i')}\n";
}

file_put_contents('debug_output.txt', ob_get_clean());

