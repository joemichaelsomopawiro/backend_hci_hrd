<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    // Check if column already exists
    $exists = \Illuminate\Support\Facades\Schema::hasColumn('pr_programs', 'start_date');
    echo "start_date column exists: " . ($exists ? 'YES' : 'NO') . "\n";

    // Also try a direct query
    $cols = \Illuminate\Support\Facades\DB::select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pr_programs' AND COLUMN_NAME = 'start_date'");
    echo "Direct INFORMATION_SCHEMA check: " . (count($cols) > 0 ? 'Found' : 'Not found') . "\n";

    // Count all columns
    $allCols = \Illuminate\Support\Facades\DB::select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pr_programs' AND TABLE_SCHEMA = DATABASE() ORDER BY ORDINAL_POSITION");
    echo "Total columns: " . count($allCols) . "\n";
    foreach ($allCols as $c) {
        echo " - " . $c->COLUMN_NAME . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
