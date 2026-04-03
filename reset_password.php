<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

$user = User::where('email', 'manager@example.com')->first();
if ($user) {
    $user->password = bcrypt('password');
    $user->save();
    echo "PASSWORD_RESET_SUCCESS: manager@example.com / password\n";
} else {
    echo "USER_NOT_FOUND\n";
}
