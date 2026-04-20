<?php
// C:\laragon\www\backend_hci_hrd\scratch\check_arranger_user.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\MusicArrangement;

$nik = '1234567890123456';
$user = User::where('employee_id', $nik)->orWhere('username', $nik)->first();

if (!$user) {
    echo "User not found\n";
    die();
}

echo "User ID: {$user->id}\n";
echo "User Name: {$user->name}\n";
echo "User Role: '{$user->role}'\n";
echo "Normalized Role: " . str_replace([' ', '&', '-'], '_', strtolower($user->role ?? '')) . "\n";

$arrs = MusicArrangement::where('created_by', $user->id)
    ->orWhere('reviewed_by', $user->id)
    ->get();

echo "Associated Arrangements: " . $arrs->count() . "\n";
foreach ($arrs as $a) {
    echo "Ep: {$a->episode_id}, Status: {$a->status}, Created: {$a->created_at}, ArrSub: {$a->arrangement_submitted_at}\n";
}
