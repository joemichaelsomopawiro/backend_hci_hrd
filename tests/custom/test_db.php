<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Testing DB Connection...\n";
    \Illuminate\Support\Facades\DB::connection()->getPdo();
    echo "DB Connection SUCCESS.\n";

    $userCount = \App\Models\User::count();
    echo "User Count: $userCount\n";

} catch (\Exception $e) {
    echo "DB Connection FAILED: " . $e->getMessage() . "\n";
}
