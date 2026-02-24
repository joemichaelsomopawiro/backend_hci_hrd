<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tables = ['pr_programs', 'pr_episodes', 'pr_episode_works', 'pr_produksi_works', 'pr_promotion_works', 'pr_editor_works', 'pr_design_grafis_works', 'pr_distribusi_works'];

foreach ($tables as $table) {
    // Check table exists
    $exists = \Illuminate\Support\Facades\DB::select("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$table}'");
    if (!$exists[0]->cnt) {
        echo "Table {$table} does not exist, skipping.\n";
        continue;
    }

    $cols = \Illuminate\Support\Facades\DB::select(
        "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
         FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = DATABASE() 
         AND TABLE_NAME = '{$table}'
         AND IS_NULLABLE = 'NO'
         AND COLUMN_DEFAULT IS NULL
         AND EXTRA NOT LIKE '%auto_increment%'
         ORDER BY ORDINAL_POSITION"
    );

    if (empty($cols)) {
        echo "Table {$table}: no non-nullable columns to fix.\n";
        continue;
    }

    echo "\n=== Table: {$table} ===\n";
    foreach ($cols as $col) {
        echo "  {$col->COLUMN_NAME} ({$col->COLUMN_TYPE})";
        try {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `{$col->COLUMN_NAME}` {$col->COLUMN_TYPE} NULL DEFAULT NULL");
            echo " -> Made nullable ✓\n";
        } catch (\Exception $e) {
            echo " -> ERROR: " . $e->getMessage() . "\n";
        }
    }
}
echo "\nAll done!\n";
