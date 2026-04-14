<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PrProgram;

$programs = PrProgram::all(['name', 'id']);
foreach ($programs as $p) {
    echo "ID: {$p->id} | Name: {$p->name}\n";
}
