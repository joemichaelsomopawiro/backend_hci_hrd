<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

use App\Models\PrQualityControlWork;
use App\Models\PrDesignGrafisWork;

$buffer = "";
$buffer .= "Checking latest QC Works...\n";

try {
    $qcWorks = PrQualityControlWork::orderBy('id', 'desc')->take(3)->get();

    if ($qcWorks->isEmpty()) {
        $buffer .= "No QC Works found.\n";
    }

    foreach ($qcWorks as $qc) {
        $buffer .= "QC Work ID: {$qc->id}, Episode ID: {$qc->pr_episode_id}, Status: {$qc->status}\n";
        $checklist = $qc->qc_checklist ?? [];
        $buffer .= "  Checklist: " . json_encode($checklist) . "\n";

        $designWork = PrDesignGrafisWork::where('pr_episode_id', $qc->pr_episode_id)->first();
        if ($designWork) {
            $buffer .= "  -> Linked Design Work ID: {$designWork->id}, Status: '{$designWork->status}'\n";
            $buffer .= "  -> Note Count: " . strlen($designWork->notes) . "\n";
            $buffer .= "  -> Notes excerpt: " . substr($designWork->notes, 0, 50) . "\n";
        } else {
            $buffer .= "  -> No Linked Design Work found for Episode {$qc->pr_episode_id}\n";
        }
        $buffer .= "---------------------------------------------------\n";
    }
} catch (\Exception $e) {
    $buffer .= "Error: " . $e->getMessage() . "\n";
}

file_put_contents('status_log.txt', $buffer);
echo "Log written to status_log.txt\n";
