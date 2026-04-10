<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
$key = 'Tables_in_' . env('DB_DATABASE');

foreach ($tables as $table) {
    if (isset($table->{$key})) {
        $tableName = $table->{$key};
        if (strpos($tableName, 'music') !== false || strpos($tableName, 'episode') !== false || strpos($tableName, 'program') !== false) {
             echo $tableName . PHP_EOL;
        }
    } else {
        // Fallback for getting values if $key mapping fails
        $vals = array_values((array)$table);
        $tableName = $vals[0];
        if (strpos($tableName, 'music') !== false || strpos($tableName, 'episode') !== false || strpos($tableName, 'program') !== false) {
             echo $tableName . PHP_EOL;
        }
    }
}
