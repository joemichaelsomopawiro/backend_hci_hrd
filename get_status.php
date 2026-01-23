<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Fetching distinct statuses...\n";
    $statuses = Illuminate\Support\Facades\DB::table('episodes')->select('status')->distinct()->pluck('status');
    echo "Found " . $statuses->count() . " statuses:\n";
    foreach ($statuses as $status) {
        echo "- '" . $status . "'\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
