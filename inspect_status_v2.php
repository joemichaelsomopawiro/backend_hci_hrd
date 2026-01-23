<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Inspecting 'episodes' table 'status' column...\n";
    $columns = DB::select("SHOW COLUMNS FROM episodes WHERE Field = 'status'");
    if (!empty($columns)) {
        echo "Type: " . $columns[0]->Type . "\n";
    } else {
        echo "Column 'status' not found.\n";
    }

    echo "\nSample existing statuses:\n";
    $statuses = DB::select("SELECT DISTINCT status FROM episodes LIMIT 5");
    foreach ($statuses as $s) {
        echo "- " . $s->status . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
