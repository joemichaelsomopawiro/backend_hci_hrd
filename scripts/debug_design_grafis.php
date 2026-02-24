<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PrDesignGrafisWork;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Debug Design Grafis Works ===\n\n";

// Check table structure
echo "1. Checking table structure...\n";
$columns = Schema::getColumnListing('pr_design_grafis_works');
echo "Columns in pr_design_grafis_works:\n";
print_r($columns);
echo "\n";

// Check table column types
echo "2. Checking column types...\n";
$columnDetails = DB::select("DESCRIBE pr_design_grafis_works");
foreach ($columnDetails as $column) {
    echo "- {$column->Field}: {$column->Type}\n";
}
echo "\n";

// Check if there's data
echo "3. Counting records...\n";
$count = DB::table('pr_design_grafis_works')->count();
echo "Total records: {$count}\n\n";

// Try to fetch without relationships
echo "4. Trying to fetch records without relationships...\n";
try {
    $works = DB::table('pr_design_grafis_works')->first();
    if ($works) {
        echo "Sample record (raw):\n";
        print_r($works);
    } else {
        echo "No records found\n";
    }
} catch (\Exception $e) {
    echo "Error fetching raw: " . $e->getMessage() . "\n";
}
echo "\n";

// Try to fetch with model
echo "5. Trying to fetch with model (no relationships)...\n";
try {
    $work = PrDesignGrafisWork::first();
    if ($work) {
        echo "Sample record (model):\n";
        print_r($work->toArray());
    } else {
        echo "No records found\n";
    }
} catch (\Exception $e) {
    echo "Error with model: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
echo "\n";

// Try to fetch with relationships
echo "6. Trying to fetch with relationships...\n";
try {
    $work = PrDesignGrafisWork::with(['episode', 'productionWork', 'promotionWork'])->first();
    if ($work) {
        echo "Successfully loaded with relationships!\n";
    } else {
        echo "No records found\n";
    }
} catch (\Exception $e) {
    echo "Error with relationships: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
echo "\n";

echo "=== Debug Complete ===\n";
