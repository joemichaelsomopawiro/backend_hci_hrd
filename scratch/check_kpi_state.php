<?php

use App\Models\KpiPointSetting;
use App\Models\User;
use App\Services\KpiService;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rolesToCheck = ['musik_arr_song', 'musik_arr_lagu', 'producer_acc_song', 'producer_acc_lagu'];

echo "--- KPI POINT SETTINGS ---\n";
foreach ($rolesToCheck as $role) {
    $setting = KpiPointSetting::where('role', $role)->where('program_type', 'musik')->first();
    if ($setting) {
        echo "Role: {$role} | Points: {$setting->points_on_time} (On Time), {$setting->points_late} (Late), {$setting->points_not_done} (Not Done)\n";
    } else {
        echo "Role: {$role} | NOT FOUND in DB\n";
    }
}

$user = User::where('name', 'LIKE', '%Music Arranger Test%')->first();
if ($user) {
    echo "\n--- KPI DATA FOR USER: {$user->name} ---\n";
    $service = new KpiService();
    $points = $service->calculateWorkPoints($user, 4, 2026);
    
    foreach ($points['items'] as $item) {
        echo "Ep: {$item['episode_number']} | Role: {$item['role_label']} ({$item['role']}) | Status: {$item['status']} | Points: {$item['points']} | Backup: " . ($item['is_backup'] ? 'YES' : 'NO') . "\n";
    }
}
