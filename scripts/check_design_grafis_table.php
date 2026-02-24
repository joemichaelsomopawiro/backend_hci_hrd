<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Design Grafis Works Check ===\n\n";

// Check table exists
try {
    $tableExists = DB::select("SHOW TABLES LIKE 'pr_design_grafis_works'");
    if (empty($tableExists)) {
        echo "ERROR: Table 'pr_design_grafis_works' does NOT exist!\n";
        echo "This is the root cause of the 404 error.\n";
        exit(1);
    }
    echo "✓ Table 'pr_design_grafis_works' exists\n\n";
} catch (\Exception $e) {
    echo "ERROR checking table: " . $e->getMessage() . "\n";
    exit(1);
}

// Count total records
$count = DB::table('pr_design_grafis_works')->count();
echo "Total records in pr_design_grafis_works: $count\n\n";

if ($count > 0) {
    echo "Sample records:\n";
    $records = DB::table('pr_design_grafis_works')
        ->limit(5)
        ->get();

    foreach ($records as $record) {
        echo "  - ID: {$record->id}, Episode: {$record->pr_episode_id}, Status: {$record->status}\n";
    }
} else {
    echo "⚠️ No Design Grafis works found in database!\n";
    echo "This explains why the dashboard is empty.\n\n";

    // Check if there are episodes with completed step 5
    $completedStep5 = DB::table('pr_episode_workflow_progress')
        ->where('workflow_step', 5)
        ->where('status', 'completed')
        ->count();

    echo "Episodes with Step 5 completed: $completedStep5\n";

    if ($completedStep5 > 0) {
        echo "\n⚠️ PROBLEM FOUND: Step 5 is completed but Design Grafis works are not being created!\n";
        echo "The workflow logic in PrPromosiController->checkAndUpdateWorkflowStep5() is not running correctly.\n";
    }
}

echo "\n=== Check Complete ===\n";
