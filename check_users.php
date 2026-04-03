<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Constants\Role;

$pmRole = Role::PROGRAM_MANAGER;
$user = User::where('role', $pmRole)->first();

if ($user) {
    echo "PM_EMAIL: {$user->email}\n";
} else {
    echo "NO_PM_FOUND\n";
    // Create one
    $newUser = User::create([
        'name' => 'Test PM',
        'email' => 'pm@test.com',
        'password' => bcrypt('password'),
        'role' => $pmRole,
        'email_verified_at' => now(),
        'status' => 'active'
    ]);
    echo "CREATED_PM: pm@test.com / password\n";
}
