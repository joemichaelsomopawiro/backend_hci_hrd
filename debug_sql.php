<?php

use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Attempting raw SQL update...\n";
    // Using parameter binding to simulate actual usage
    $affected = DB::update("UPDATE program_schedule_options SET review_notes = ? WHERE id = 7", ['no']);
    echo "Update successful! Affected rows: $affected\n";
} catch (\Exception $e) {
    echo "Update FAILED: " . $e->getMessage() . "\n";
}

try {
    echo "Attempting raw SQL update (longer text)...\n";
    $text = "This is a longer text to verify that the text column actually works as expected.";
    $affected = DB::update("UPDATE program_schedule_options SET review_notes = ? WHERE id = 7", [$text]);
    echo "Update (long) successful! Affected rows: $affected\n";
} catch (\Exception $e) {
    echo "Update (long) FAILED: " . $e->getMessage() . "\n";
}
