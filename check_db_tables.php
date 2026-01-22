<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Checking database tables...\n\n";

try {
    $tables = \DB::select('SHOW TABLES');
    echo "✓ Database connected\n";
    echo "Total tables: " . count($tables) . "\n\n";

    $prTables = array_filter($tables, function ($table) {
        $tableName = array_values((array) $table)[0];
        return str_starts_with($tableName, 'pr_');
    });

    echo "PR Tables:\n";
    foreach ($prTables as $table) {
        $tableName = array_values((array) $table)[0];
        echo "  - {$tableName}\n";
    }

    // Check specifically for pr_program_crews
    $hasCrewsTable = false;
    foreach ($tables as $table) {
        $tableName = array_values((array) $table)[0];
        if ($tableName === 'pr_program_crews') {
            $hasCrewsTable = true;
            break;
        }
    }

    echo "\n";
    if ($hasCrewsTable) {
        echo "✓ pr_program_crews table EXISTS\n";

        // Check columns
        $columns = \DB::select('DESCRIBE pr_program_crews');
        echo "\nColumns:\n";
        foreach ($columns as $col) {
            echo "  - {$col->Field} ({$col->Type})\n";
        }
    } else {
        echo "❌ pr_program_crews table NOT FOUND\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
}
