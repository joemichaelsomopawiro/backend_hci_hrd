<?php
use App\Models\PrManagerDistribusiQcWork;
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$w2 = PrManagerDistribusiQcWork::find(2);
if ($w2) {
    echo "ID 2 -> EpID: " . $w2->pr_episode_id . " | Status: " . $w2->status . "\n";
} else {
    echo "ID 2 not found\n";
}

$w3 = PrManagerDistribusiQcWork::find(3);
if ($w3) {
    echo "ID 3 -> EpID: " . $w3->pr_episode_id . " | Status: " . $w3->status . "\n";
}

$ep1119_qc = PrManagerDistribusiQcWork::where('pr_episode_id', 1119)->first();
if ($ep1119_qc) {
    echo "EpID 1119 (Program Test Ep 2) QC Work ID: " . $ep1119_qc->id . " | Status: " . $ep1119_qc->status . "\n";
} else {
    echo "EpID 1119 QC Work NOT FOUND\n";
}
