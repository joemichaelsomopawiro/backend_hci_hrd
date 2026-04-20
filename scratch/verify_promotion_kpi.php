<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\KpiService;
use App\Models\User;

$service = new KpiService();
// PT user ID is usually 17 based on the NIK 1234567890123017 provided in screenshot
$user = User::where('name', 'like', '%PT%')->orWhere('id', 17)->first();

if (!$user) {
    echo "Promotion User (PT) not found\n";
    exit;
}

echo "Testing KPI for User: " . $user->name . " (ID: " . $user->id . ")\n";

$month = 4; // April
$year = 2026;

$kpi = $service->calculateWorkPoints($user, $month, $year);

echo "\nKPI Summary for April 2026:\n";
echo "Total Points: " . $kpi['total_points'] . "\n";
echo "Max Points: " . $kpi['max_points'] . "\n";
echo "Percentage: " . $kpi['percentage'] . "%\n";

echo "\nItems Breakdown:\n";
foreach ($kpi['items'] as $item) {
    printf("- [%s] Ep %s: %s | Status: %s | Points: %s/%s\n", 
        $item['program_name'], 
        $item['episode_number'] ?? 'N/A', 
        $item['role_label'], 
        $item['status'], 
        $item['points'],
        $item['max_points']
    );
}
