<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

try {
    echo "Testing PrProduksiWork query...\n";
    $works = \App\Models\PrProduksiWork::with([
        'episode.program',
        'episode.files',
        'creativeWork',
        'createdBy',
        'equipmentLoan.loanItems.inventoryItem',
        'editorWork'
    ])->take(5)->get();
    echo "Query successful. Count: " . $works->count() . "\n";

    foreach ($works as $work) {
        if ($work->editorWork) {
            echo "Work ID: {$work->id}, Editor Work ID: {$work->editorWork->id}, File Notes: '{$work->editorWork->file_notes}'\n";
        } else {
            echo "Work ID: {$work->id}, No Editor Work linked.\n";
        }
    }

    foreach ($works as $work) {
        if ($work->editorWork) {
            echo "Work ID: {$work->id}, Editor Work ID: {$work->editorWork->id}, File Notes: '{$work->editorWork->file_notes}'\n";
        } else {
            echo "Work ID: {$work->id}, No Editor Work linked.\n";
        }
    }
} catch (\Exception $e) {
    echo "Query failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
