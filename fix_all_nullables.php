<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get all columns that are NOT NULL and have no default (excluding auto_increment)
$cols = \Illuminate\Support\Facades\DB::select(
    "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_TYPE
     FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'pr_programs'
     AND IS_NULLABLE = 'NO'
     AND COLUMN_DEFAULT IS NULL
     AND EXTRA NOT LIKE '%auto_increment%'
     ORDER BY ORDINAL_POSITION"
);

echo "=== Non-nullable columns without defaults ===\n";
foreach ($cols as $col) {
    echo $col->COLUMN_NAME . " | " . $col->COLUMN_TYPE . "\n";
    // Make nullable
    try {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE pr_programs MODIFY COLUMN `{$col->COLUMN_NAME}` {$col->COLUMN_TYPE} NULL DEFAULT NULL");
        echo "  -> Made nullable ✓\n";
    } catch (\Exception $e) {
        echo "  -> ERROR: " . $e->getMessage() . "\n";
    }
}
echo "\nDone!\n";
