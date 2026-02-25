<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $cols = \Illuminate\Support\Facades\DB::select('DESCRIBE pr_programs');
    echo "=== ALL COLUMNS ===\n";
    foreach ($cols as $c) {
        echo $c->Field . " | " . $c->Type . " | Null=" . $c->Null . " | Default=" . var_export($c->Default, true) . " | Extra=" . $c->Extra . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
