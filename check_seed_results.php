<?php

use App\Models\User;
use App\Constants\Role;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$expectedRoles = array_merge(Role::getAllStandardRoles(), ['backend', 'senior developer']);
$missing = [];
$created = [];

foreach ($expectedRoles as $role) {
    // Logic for email must match Seeder
    $emailPrefix = strtolower(str_replace(' ', '', $role));
    $email = $emailPrefix . '@joe.com';

    $user = User::where('email', $email)->first();

    if ($user) {
        $created[] = [
            'role' => $role,
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'password'
        ];
    } else {
        $missing[] = $role;
    }
}

$output = "=== CREATED USERS (" . count($created) . ") ===\n";
foreach ($created as $u) {
    $output .= "[OK] {$u['role']} -> {$u['email']}\n";
}

$output .= "\n=== MISSING ROLES (" . count($missing) . ") ===\n";
foreach ($missing as $r) {
    $output .= "[MISSING] {$r}\n";
}

file_put_contents('created_users.txt', $output);
echo "Result written to created_users.txt\n";
