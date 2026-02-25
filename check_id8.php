<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$ew = \App\Models\EditorWork::find(8);
if ($ew) {
    echo "ID: " . $ew->id . PHP_EOL;
    echo "Status: " . $ew->status . PHP_EOL;
    echo "Created By: " . $ew->created_by . PHP_EOL;
    echo "QC Feedback: " . $ew->qc_feedback . PHP_EOL;
} else {
    echo "Not found" . PHP_EOL;
}
