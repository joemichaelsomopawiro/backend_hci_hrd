<?php

require_once __DIR__ . '/public/index.php';

use App\Models\User;
use App\Models\BroadcastingWork;
use App\Models\Episode;
use App\Services\KpiService;
use Illuminate\Support\Facades\DB;

// Diagnostic Script for Distribution Manager KPI
echo "--- Distribution Manager KPI Diagnostic ---\n";

try {
    // 1. Find a Distribution Manager user
    $user = User::where('role', 'Distribution Manager')->first();
    if (!$user) {
        die("No Distribution Manager user found for test.\n");
    }
    echo "Testing User: {$user->name} (Role: {$user->role})\n";

    // 2. Mock a BroadcastingWork for this user
    $episode = Episode::first();
    if (!$episode) {
        die("No episode found for test.\n");
    }
    echo "Testing Episode: #{$episode->episode_number} (ID: {$episode->id})\n";

    // Start transaction to mock data safely
    DB::beginTransaction();

    $work = BroadcastingWork::create([
        'episode_id' => $episode->id,
        'approved_by' => $user->id,
        'approved_at' => now(),
        'status' => 'pending',
        'work_type' => 'youtube_upload',
        'title' => 'Test Broadcasting Work',
        'created_by' => $user->id
    ]);
    echo "Created mock BroadcastingWork ID: {$work->id}\n";

    // 3. Run KPI Service point collection
    $kpiService = app(KpiService::class);
    $month = now()->month;
    $year = now()->year;
    
    // Use calculateWorkPoints which is the main discovery engine for workflow points
    $kpi = $kpiService->calculateWorkPoints($user, $month, $year);

    $foundCount = 0;
    // calculateWorkPoints returns an array with 'items' key
    foreach ($kpi['items'] as $item) {
        if ($item['episode_id'] == $episode->id && $item['role'] === 'distribution_manager_qc') {
            echo "✅ SUCCESS: Found KPI item with role 'distribution_manager_qc' for episode #{$episode->episode_number}\n";
            echo "   Points: {$item['points']} / Max: {$item['max_points']}\n";
            $foundCount++;
        }
    }

    if ($foundCount === 0) {
        echo "❌ FAILURE: KPI item with role 'distribution_manager_qc' NOT FOUND.\n";
    }

    // Rollback changes
    DB::rollBack();
    echo "Mock data rolled back.\n";

} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
