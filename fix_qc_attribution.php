<?php

use App\Models\QualityControlWork;
use App\Models\DesignGrafisWork;
use App\Models\Episode;
use App\Models\Program;
use App\Models\PromotionWork;

// Bootstrap Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

echo "Starting QC attribution repair script...\n";

// 1. Identify Episode 19 of Program Musik 1
$program = Program::where('name', 'LIKE', '%Musik 1%')->first();
if (!$program) {
    die("Program Musik 1 not found.\n");
}

$episode = Episode::where('program_id', $program->id)
    ->where('episode_number', 19)
    ->first();

if (!$episode) {
    die("Episode 19 not found for Program {$program->name}.\n");
}

echo "Found Episode ID: {$episode->id} (#{$episode->episode_number} - {$episode->title})\n";

// 2. Fix Design Grafis QC records for this episode
$designWorks = DesignGrafisWork::where('episode_id', $episode->id)->get();
foreach ($designWorks as $dw) {
    $qcTypeMap = [
        'thumbnail_youtube' => 'thumbnail_yt',
        'thumbnail_bts' => 'thumbnail_bts'
    ];
    
    $qcType = $qcTypeMap[$dw->work_type] ?? null;
    if (!$qcType) continue;

    $qcWork = QualityControlWork::where('episode_id', $episode->id)
        ->where('qc_type', $qcType)
        ->first();

    if ($qcWork && $dw->assigned_to) {
        if ($qcWork->created_by != $dw->assigned_to) {
            echo "Repairing Design QC (#{$qcWork->id}) for {$dw->work_type}: Changing creator from {$qcWork->created_by} to worker {$dw->assigned_to}\n";
            $qcWork->update(['created_by' => $dw->assigned_to]);
        } else {
            echo "Design QC (#{$qcWork->id}) already has correct attribution.\n";
        }
    }
}

// 3. Fix Promotion Work (Editor Promosi) QC records for this episode
$promotionWorks = PromotionWork::where('episode_id', $episode->id)->get();
$qcWorkPromosi = QualityControlWork::where('episode_id', $episode->id)
    ->where('qc_type', 'bts_video') // Standard type for Editor Promosi QC
    ->first();

if ($qcWorkPromosi && $promotionWorks->isNotEmpty()) {
    // Find the first Editor Promosi who contributed to this episode's promotion works
    $workerId = null;
    foreach ($promotionWorks as $pw) {
        if ($pw->assigned_to) {
            $workerId = $pw->assigned_to;
            break;
        }
    }
    
    if ($workerId && $qcWorkPromosi->created_by != $workerId) {
        echo "Repairing Promotion QC (#{$qcWorkPromosi->id}): Changing creator from {$qcWorkPromosi->created_by} to worker {$workerId}\n";
        $qcWorkPromosi->update(['created_by' => $workerId]);
    }
}

echo "Repair complete.\n";
