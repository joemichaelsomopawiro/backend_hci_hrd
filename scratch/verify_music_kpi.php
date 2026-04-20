<?php

use App\Models\KpiPointSetting;
use App\Models\User;
use App\Services\KpiService;
use App\Models\ProductionEquipment;
use App\Models\Episode;
use Carbon\Carbon;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 1. Check for Art Set Properti points for a User who approves loans
$userArtSet = User::where('role', 'LIKE', '%Art%')->first();
if ($userArtSet) {
    echo "--- Testing Art Set Properti KPI (Music) ---\n";
    echo "User: {$userArtSet->name} (ID: {$userArtSet->id})\n";
    
    $service = new KpiService();
    $points = $service->calculateWorkPoints($userArtSet, now()->month, now()->year);
    
    $musicArtItems = collect($points['items'])->where('program_type', 'musik')->where('role', 'LIKE', 'art_set%');
    
    if ($musicArtItems->isEmpty()) {
        echo "No Music Art Set items found for this month.\n";
    } else {
        foreach ($musicArtItems as $item) {
            echo "Role: {$item['role']} | Ep: {$item['episode_number']} | Status: {$item['status']} | Points: {$item['points']} (Expected if on-time: 2.0)\n";
        }
    }
}

// 2. Check for Tim Setting points for a User in a music episode
$userSetting = User::where('role', 'LIKE', '%Setting%')->orWhere('role', 'LIKE', '%Production%')->first();
if ($userSetting) {
    echo "\n--- Testing Tim Setting KPI (Music) ---\n";
    echo "User: {$userSetting->name} (ID: {$userSetting->id})\n";
    
    $service = new KpiService();
    $points = $service->calculateWorkPoints($userSetting, now()->month, now()->year);
    
    $settingItems = collect($points['items'])->where('program_type', 'musik')->where('role', 'LIKE', 'tim_setting%');
    
    if ($settingItems->isEmpty()) {
        echo "No Music Setting items found for this month.\n";
    } else {
        foreach ($settingItems as $item) {
            echo "Role: {$item['role']} | Ep: {$item['episode_number']} | Status: {$item['status']} | Points: {$item['points']}\n";
        }
    }
}
