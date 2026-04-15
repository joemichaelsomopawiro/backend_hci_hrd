<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\KpiService;
use Carbon\Carbon;

echo "--- KPI CALCULATION VERIFICATION ---\n";

$kpiService = new KpiService();
$user = User::findOrFail(4); // Kreatif HC

$month = 4; // April
$year = 2026;

echo "User: {$user->name} (ID: {$user->id})\n";
echo "Period: " . Carbon::create($year, $month)->format('F Y') . "\n\n";

$workPoints = $kpiService->calculateWorkPoints($user, $month, $year);

echo "Total Points: " . $workPoints['total_points'] . "\n";
echo "Max Points: " . $workPoints['max_points'] . "\n";
echo "Percentage: " . $workPoints['percentage'] . "%\n";
echo "Total Tasks: " . $workPoints['breakdown']['total_tasks'] . "\n";

echo "\n--- SAMPLE ITEMS ---\n";
// Show first 5 items
$items = array_slice($workPoints['items'], 0, 5);
foreach ($items as $item) {
    echo "- Episode #{$item['episode_id']} | Role: {$item['role']} | Points: {$item['points']} | Status: {$item['status']}\n";
}

if (count($workPoints['items']) > 0) {
    echo "\nRESULT: KPI Integration confirmed! Tasks are appearing in calculation.\n";
} else {
    echo "\nRESULT: No tasks found. Check assignment logic.\n";
}
