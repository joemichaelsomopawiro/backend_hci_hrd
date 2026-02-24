<?php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$qcWork = \App\Models\PrManagerDistribusiQcWork::where('id', 1)->first();

if ($qcWork && $qcWork->qc_checklist) {
    $checklist = $qcWork->qc_checklist;
    $checklistUpdated = false;

    foreach ($checklist as $key => $item) {
        if (($item['status'] ?? '') === 'revision') {
            $checklist[$key]['status'] = 'revised';
            $checklistUpdated = true;
        }
    }

    if ($checklistUpdated) {
        // Try assigning directly to ensure it gets marked as dirty
        $qcWork->qc_checklist = $checklist;
        $qcWork->save();
        echo "Updated!\n";
    }
} else {
    echo "Not found or no checklist";
}

$fresh = \App\Models\PrManagerDistribusiQcWork::where('id', 1)->first();
file_put_contents('db_clean_out.json', json_encode($fresh->qc_checklist, JSON_PRETTY_PRINT));
echo "Done";
