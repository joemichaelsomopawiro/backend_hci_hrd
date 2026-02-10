<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking for Promotion Role Users ===\n";
$promotionUsers = \App\Models\User::where('role', 'Promotion')->get();
echo "Found " . $promotionUsers->count() . " users with role 'Promotion'\n\n";

foreach ($promotionUsers as $user) {
    echo "- ID: {$user->id}, Name: {$user->name}, Email: {$user->email}\n";
}

echo "\n=== All Available Roles ===\n";
$roles = \App\Models\User::distinct()->pluck('role');
foreach ($roles as $role) {
    $count = \App\Models\User::where('role', $role)->count();
    echo "- {$role}: {$count} users\n";
}
