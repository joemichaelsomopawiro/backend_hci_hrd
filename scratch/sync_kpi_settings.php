<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\KpiPointSetting;
use Illuminate\Support\Facades\DB;

echo "Syncing KPI Point Settings from Defaults...\n";

$defaults = KpiPointSetting::getDefaults();
$count = 0;

foreach ($defaults as $setting) {
    KpiPointSetting::updateOrCreate(
        ['role' => $setting['role'], 'program_type' => $setting['program_type']],
        $setting
    );
    $count++;
}

echo "Successfully synced {$count} KPI settings.\n";
