<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $result = DB::select("SHOW CREATE TABLE episodes");
    // The key might be 'Create Table' or 'Create View' depending on driver, usually 'Create Table' for mysql
    $row = (array) $result[0];
    print_r($row['Create Table']);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
