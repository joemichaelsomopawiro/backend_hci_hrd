<?php

use App\Constants\Role;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Role::PROGRAM_MANAGER is: " . Role::PROGRAM_MANAGER . "\n";
    echo "Test success.\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
