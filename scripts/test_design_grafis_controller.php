<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PrDesignGrafisWork;
use Illuminate\Support\Facades\DB;

echo "=== Testing Design Grafis Controller Logic ===\n\n";

try {
    echo "Simulating controller index() method...\n";

    $query = PrDesignGrafisWork::with([
        'episode.program',
        'productionWork.uploadedFiles',
        'promotionWork.talentPhotos',
        'assignedUser'
    ]);

    $works = $query->orderBy('created_at', 'desc')->get();

    echo "Query executed successfully!\n";
    echo "Found {$works->count()} works\n\n";

    // Add deadline calculation for each work
    $works->each(function ($work) {
        echo "Processing work ID: {$work->id}\n";
        if (!$work->deadline && $work->episode && $work->episode->air_date) {
            $work->deadline = \Carbon\Carbon::parse($work->episode->air_date)->subDay();
            echo "  - Calculated deadline: {$work->deadline}\n";
        }
    });

    echo "\nSuccess! No errors encountered.\n";

} catch (\Exception $e) {
    echo "\n=== ERROR ===\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
