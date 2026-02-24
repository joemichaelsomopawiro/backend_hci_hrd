<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tables = ['pr_episodes', 'pr_produksi_works', 'pr_promotion_works', 'pr_editor_works', 'pr_editor_promosi_works', 'pr_design_grafis_works'];

foreach ($tables as $table) {
    $exists = \Illuminate\Support\Facades\DB::select("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$table}'");
    if (!$exists[0]->cnt) {
        echo "TABLE NOT FOUND: {$table}\n";
        continue;
    }

    $cols = \Illuminate\Support\Facades\DB::select(
        "SELECT COLUMN_NAME, COLUMN_TYPE
         FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = DATABASE() 
         AND TABLE_NAME = '{$table}'
         AND DATA_TYPE IN ('enum', 'set')
         ORDER BY ORDINAL_POSITION"
    );

    if (!empty($cols)) {
        echo "\n=== {$table} ===\n";
        foreach ($cols as $col) {
            echo "  {$col->COLUMN_NAME}: {$col->COLUMN_TYPE}\n";
        }
    } else {
        echo "{$table}: no ENUM columns\n";
    }
}
