<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$database = config('database.connections.mysql.database');
$tables = DB::select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME = 'employee_id' AND TABLE_SCHEMA = ?", [$database]);

echo "Tables with employee_id column:\n";
foreach ($tables as $t) {
    echo "- {$t->TABLE_NAME}\n";
}
