<?php

use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$info = DB::select("SHOW COLUMNS FROM program_schedule_options WHERE Field = 'status'");
if (!empty($info)) {
    echo "Status Column Type: " . $info[0]->Type . "\n";
} else {
    echo "Column status not found\n";
}
