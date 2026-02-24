<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

try {
    echo "Listing tables...\n";
    $tables = DB::select('SHOW TABLES');
    $tableNames = array_map(function ($t) {
        return array_values((array) $t)[0];
    }, $tables);

    foreach ($tableNames as $name) {
        if (strpos($name, 'works') !== false || strpos($name, 'pr_') !== false) {
            echo $name . "\n";
        }
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
