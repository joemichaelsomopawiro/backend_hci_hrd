<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$columns = Schema::getColumnListing('program_schedule_options');
echo "Columns: " . implode(', ', $columns) . "\n\n";

$type = Schema::getColumnType('program_schedule_options', 'review_notes');
echo "Review Notes Type (Schema): " . $type . "\n";

// Get raw info
$info = DB::select("SHOW COLUMNS FROM program_schedule_options WHERE Field = 'review_notes'");
if (!empty($info)) {
    echo "Raw Type: " . $info[0]->Type . "\n";
} else {
    echo "Column review_notes not found in SHOW COLUMNS\n";
}
