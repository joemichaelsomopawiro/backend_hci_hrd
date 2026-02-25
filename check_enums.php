<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Show ENUM columns
$cols = \Illuminate\Support\Facades\DB::select(
    "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
     FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'pr_programs'
     AND DATA_TYPE IN ('enum', 'set')
     ORDER BY ORDINAL_POSITION"
);

echo "=== ENUM/SET columns ===\n";
foreach ($cols as $col) {
    echo $col->COLUMN_NAME . ": " . $col->COLUMN_TYPE . " | NULL=" . $col->IS_NULLABLE . " | Default=" . $col->COLUMN_DEFAULT . "\n";
}
