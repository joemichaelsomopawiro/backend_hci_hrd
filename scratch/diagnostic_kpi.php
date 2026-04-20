<?php

use App\Models\KpiPointSetting;
use App\Models\User;
use App\Services\KpiService;
use Illuminate\Support\Facades\Log;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = User::where('name', 'LIKE', '%Music Arranger Test%')->first();
if ($user) {
    Log::info("DIAGNOSTIC: User Name: {$user->name} | Role: {$user->role} | ID: {$user->id}");
    
    $normalizedRole = strtolower(str_replace([' ', '&', '-'], ['_', '', '_'], $user->role ?? ''));
    Log::info("DIAGNOSTIC: Normalized Role: {$normalizedRole}");

    $settings = KpiPointSetting::all()->keyBy(function ($item) {
        return $item->role . '_' . $item->program_type;
    });
    
    Log::info("DIAGNOSTIC: Settings keys: " . implode(', ', $settings->keys()->toArray()));
    
    $service = new KpiService();
    $points = $service->calculateWorkPoints($user, 4, 2026);
    Log::info("DIAGNOSTIC: Result count: " . count($points['items']));
    Log::info("DIAGNOSTIC: Work Items: " . json_encode($points['items']));
} else {
    Log::info("DIAGNOSTIC: User 'Music Arranger Test' NOT FOUND");
}
