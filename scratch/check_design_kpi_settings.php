<?php

use App\Models\KpiPointSetting;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$settings = KpiPointSetting::where('role', 'design_grafis')->get();

echo "Current KpiPointSetting for design_grafis:\n";
foreach ($settings as $s) {
    echo "Role: {$s->role}, Program Type: {$s->program_type}\n";
    echo "  On-time: {$s->points_on_time}\n";
    echo "  Late: {$s->points_late}\n";
    echo "  Not Done: {$s->points_not_done}\n";
    echo "  Max Quality: {$s->quality_max}\n";
    echo "--------------------------\n";
}

// Also check if there's any pending update needed based on user request:
// On-time: 3, Late: 1, Not done: -5, Quality: 5
