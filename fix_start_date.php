<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    // Check current definition of start_date
    $col = \Illuminate\Support\Facades\DB::select(
        "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
         FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = DATABASE() 
         AND TABLE_NAME = 'pr_programs' 
         AND COLUMN_NAME = 'start_date'"
    );
    echo "Current start_date definition:\n";
    print_r($col);

    // Make it nullable
    \Illuminate\Support\Facades\DB::statement("ALTER TABLE pr_programs MODIFY COLUMN start_date date NULL DEFAULT NULL");
    echo "\nAltered start_date to be nullable.\n";

    // Verify
    $col2 = \Illuminate\Support\Facades\DB::select(
        "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
         FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = DATABASE() 
         AND TABLE_NAME = 'pr_programs' 
         AND COLUMN_NAME = 'start_date'"
    );
    echo "After fix:\n";
    print_r($col2);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
