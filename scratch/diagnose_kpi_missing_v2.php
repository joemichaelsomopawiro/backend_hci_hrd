<?php

use App\Models\User;
use App\Models\Episode;
use App\Models\EditorPromosiWork;
use App\Models\DesignGrafisWork;
use App\Models\KpiService;
use Carbon\Carbon;

// Mock user (The one from the screenshot)
$userId = 15; // User ID for "Editor Promotion Test" - based on pattern or previous info if available
// If user ID is unknown, we can find by NIK
$user = User::where('nik', '1234567890123014')->first();
$userId = $user->id;

echo "User: " . $user->name . " (ID: $userId)\n";

$month = 4; // April
$year = 2026;

// 1. Check EditorPromosiWork for this user
$works = EditorPromosiWork::where(function($q) use ($userId) {
    $q->where('created_by', $userId)
      ->orWhere('originally_assigned_to', $userId);
})->get();

echo "Total EditorPromosiWork found for user: " . $works->count() . "\n";
foreach ($works as $w) {
    echo "- Ep ID: {$w->episode_id}, Type: {$w->work_type}, Status: {$w->status}, Submitted: {$w->submitted_at}\n";
}

// 2. Check if Episode 18 (test 1) is found
$testEp = Episode::where('episode_number', 18)->first();
echo "Episode 18 ID: " . ($testEp->id ?? 'Not found') . ", Air Date: " . ($testEp->air_date ?? 'N/A') . "\n";

// 3. Test the Air Date logic
$airDate = Carbon::parse($testEp->air_date);
echo "Air Date month: " . $airDate->month . ", Target month: $month\n";

// 4. Test Activity detection for this user on Ep 18
$hasActivity = EditorPromosiWork::where('episode_id', $testEp->id)
    ->where(function($q) use ($userId) {
        $q->where('created_by', $userId)
          ->orWhere('originally_assigned_to', $userId);
    })
    ->where(function($q) use ($month, $year) {
        $q->whereMonth('submitted_at', $month)->whereYear('submitted_at', $year)
          ->orWhereMonth('updated_at', $month)->whereYear('updated_at', $year);
    })
    ->exists();

echo "Has EditorPromosi activity in month $month for Ep 18? " . ($hasActivity ? 'YES' : 'NO') . "\n";
