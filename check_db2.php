<?php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$works = \App\Models\PrManagerDistribusiQcWork::orderBy('id', 'desc')->take(5)->get();
$out = [];
foreach ($works as $w) {
    $out[] = [
        'id' => $w->id,
        'episode_id' => $w->pr_episode_id,
        'status' => $w->status,
        'checklist' => $w->qc_checklist
    ];
}
file_put_contents('db_clean_out.json', json_encode($out, JSON_PRETTY_PRINT));
echo "Done";
