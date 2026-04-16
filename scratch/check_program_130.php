<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Program;

$program = Program::find(130);
if ($program) {
    echo "Program 130: Name={$program->name}, Category={$program->category}\n";
} else {
    echo "Program 130 not found\n";
}
