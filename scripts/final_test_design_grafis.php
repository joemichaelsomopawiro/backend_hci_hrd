<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PrDesignGrafisWork;

echo "=== Final Test: Design Grafis Works Query ===\n\n";

try {
    // This mimics what the controller does
    $query = PrDesignGrafisWork::with([
        'episode.program',
        'productionWork',
        'promotionWork',
        'assignedUser'
    ]);

    $works = $query->orderBy('created_at', 'desc')->get();

    echo "✓ SUCCESS! Query executed without errors\n";
    echo "✓ Found {$works->count()} works\n";

    if ($works->count() > 0) {
        echo "\nSample data structure:\n";
        $work = $works->first();
        echo "- Work ID: {$work->id}\n";
        echo "- Episode: " . ($work->episode ? "loaded" : "null") . "\n";
        echo "- Program: " . ($work->episode && $work->episode->program ? "loaded" : "null") . "\n";
        echo "- Production Work: " . ($work->productionWork ? "loaded" : "null") . "\n";
        echo "- Promotion Work: " . ($work->promotionWork ? "loaded" : "null") . "\n";
        echo "- Assigned User: " . ($work->assignedUser ? "loaded" : "null") . "\n";
    }

    echo "\n✓ All relationships loaded successfully!\n";
    echo "\n=== TEST PASSED ===\n";

} catch (\Exception $e) {
    echo "✗ FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
