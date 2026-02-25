<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$works = \App\Models\PrManagerDistribusiQcWork::all();
foreach ($works as $w) {
    echo 'ID: ' . $w->id . ' Episode: ' . $w->pr_episode_id . ' Checklist: ' . json_encode($w->qc_checklist) . "\n";
}
