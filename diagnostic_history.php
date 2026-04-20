<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BroadcastingWork;

echo "📊 Broadcasting Works Summary:\n";
$statuses = BroadcastingWork::select('status', \DB::raw('count(*) as count'))
    ->groupBy('status')
    ->get();

foreach ($statuses as $s) {
    echo "- {$s->status}: {$s->count}\n";
}

echo "\n📋 Recent Works (last 5):\n";
$recent = BroadcastingWork::latest()->take(5)->get();
foreach ($recent as $work) {
    echo "- ID: {$work->id}, Status: {$work->status}, Approved At: {$work->approved_at}, Title: {$work->title}\n";
}
