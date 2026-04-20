<?php
// C:\laragon\www\backend_hci_hrd\scratch\diagnose_kpi_missing.php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\MusicArrangement;
use Illuminate\Support\Facades\DB;

$nik = '1234567890123456';
$user = User::where('employee_id', $nik)->orWhere('username', $nik)->first();

if (!$user) {
    echo "ERROR: User with NIK $nik not found!\n";
    die();
}

echo "Found User: {$user->name} (ID: {$user->id})\n";

$arrangements = MusicArrangement::where('created_by', $user->id)
    ->orWhere('reviewed_by', $user->id)
    ->get();

echo "Total arrangements associated with User ID {$user->id}: " . $arrangements->count() . "\n";

foreach ($arrangements as $m) {
    echo " - ID: {$m->id}, Episode: {$m->episode_id}, Status: {$m->status}, Created: {$m->created_at}\n";
    echo "   Activity Dates: Appr({$m->song_approved_at}), Rev({$m->reviewed_at}), ArrSub({$m->arrangement_submitted_at}), Sub({$m->submitted_at})\n";
}

$month = 4;
$year = 2026;

$activityQuery = MusicArrangement::where(function($q) use ($user) {
        $q->where('reviewed_by', $user->id)->orWhere('created_by', $user->id);
    })
    ->where(function($q) use ($month, $year) {
        $q->where(function($sq) use ($month, $year) {
            $sq->whereYear('song_approved_at', $year)->whereMonth('song_approved_at', $month);
        })->orWhere(function($sq) use ($month, $year) {
            $sq->whereYear('reviewed_at', $year)->whereMonth('reviewed_at', $month);
        })->orWhere(function($sq) use ($month, $year) {
            $sq->whereYear('created_at', $year)->whereMonth('created_at', $month);
        })->orWhere(function($sq) use ($month, $year) {
            $sq->whereYear('arrangement_submitted_at', $year)->whereMonth('arrangement_submitted_at', $month);
        });
    });

echo "Query SQL: " . $activityQuery->toSql() . "\n";
echo "Matching arrangements for April 2026: " . $activityQuery->count() . "\n";
