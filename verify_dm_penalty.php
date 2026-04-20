<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\KpiService;
use App\Models\User;
use App\Models\BroadcastingWork;
use App\Models\Episode;
use App\Models\Program;
use Carbon\Carbon;

$userId = User::where('role', 'Distribution Manager')->value('id');
if (!$userId) {
    echo "❌ No Distribution Manager found!\n";
    exit;
}

$user = User::find($userId);

echo "🧪 Testing KPI Logic for distribution_manager_qc role:\n";

// 1. Create a dummy program and episode manually
$program = Program::create([
    'name' => 'KPI Test Program',
    'status' => 'active',
    'type' => 'musik'
]);

$pastDate = Carbon::now()->subDays(15);
$pastEpisode = Episode::create([
    'program_id' => $program->id,
    'air_date' => $pastDate,
    'production_date' => $pastDate->copy()->subDays(2),
    'episode_number' => '999',
    'title' => 'KPI Penalty Test'
]);

// 3. Create a BroadcastingWork that was NOT completed
$work = BroadcastingWork::create([
    'episode_id' => $pastEpisode->id,
    'status' => 'pending_approval',
    'created_by' => 1, // dummy
]);

$kpiService = new KpiService();
// Check currently calculating month
$month = now()->month;
$year = now()->year;

$results = $kpiService->calculateWorkPoints($user, $month, $year);

$found = false;
foreach ($results['items'] as $item) {
    if ($item['role'] === 'distribution_manager_qc' && $item['episode_id'] === $pastEpisode->id) {
        $found = true;
        echo "✅ Task Found in KPI:\n";
        echo "- Role: {$item['role']}\n";
        echo "- Status: {$item['status']} (Expected: failed)\n";
        echo "- Points Given: {$item['points']} (Expected: -5)\n";
        echo "- Deadline: {$item['deadline']}\n";
    }
}

if (!$found) {
    echo "❌ Test task not found in KPI processing. Items checked: " . count($results['items']) . "\n";
    // List first 3 items for debugging
    foreach (array_slice($results['items'], 0, 3) as $it) {
        echo "  - Checked: {$it['role']} for Episode {$it['episode_number']}\n";
    }
}

// Cleanup
$work->delete();
$pastEpisode->delete();
$program->delete();
