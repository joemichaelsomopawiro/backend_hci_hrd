<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$output = "";

try {
    $output .= "Inspecting 'episodes' table 'status' column...\n";
    $columns = DB::select("SHOW COLUMNS FROM episodes WHERE Field = 'status'");
    if (!empty($columns)) {
        $output .= "Type: " . $columns[0]->Type . "\n";
    } else {
        $output .= "Column 'status' not found.\n";
    }

    $output .= "\nSample existing statuses:\n";
    $statuses = DB::select("SELECT DISTINCT status FROM episodes limit 10");
    foreach ($statuses as $s) {
        $output .= "- " . $s->status . "\n";
    }
} catch (\Exception $e) {
    $output .= "Error: " . $e->getMessage() . "\n";
}

file_put_contents('db_schema.txt', $output);
echo "Done writing to db_schema.txt\n";
