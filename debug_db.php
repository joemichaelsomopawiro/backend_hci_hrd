<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

$columns = \Illuminate\Support\Facades\DB::select('SHOW COLUMNS FROM design_grafis_works');

foreach ($columns as $column) {
    if ($column->Field === 'status') {
        echo "Column: " . $column->Field . "\n";
        echo "Type: " . $column->Type . "\n";
        echo "Null: " . $column->Null . "\n";
        echo "Default: " . $column->Default . "\n";
        echo "Extra: " . $column->Extra . "\n";
    }
}
