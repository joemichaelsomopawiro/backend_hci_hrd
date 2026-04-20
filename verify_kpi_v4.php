<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\KpiService;

$user = User::where('name', 'Promotion Test')->first();
$service = app(KpiService::class);
$year = 2026;
$month = null;

$kpi = $service->calculateWorkPoints($user, $month, $year);

echo "KPI for User: {$user->name} (Role: {$user->role})\n";
echo "Total Rows in KPI Table: " . count($kpi['items']) . "\n";
foreach ($kpi['items'] as $item) {
    echo "- Role: {$item['role_label']}, Status: {$item['status']}, Deadline: {$item['deadline']}\n";
}
