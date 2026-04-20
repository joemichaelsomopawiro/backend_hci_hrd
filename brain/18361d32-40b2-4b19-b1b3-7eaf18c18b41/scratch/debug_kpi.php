<?php

use App\Models\User;
use App\Models\Employee;
use App\Services\KpiService;
use Carbon\Carbon;

// Set target parameters
$userName = 'Sound Engineer Test';
$month = 4;
$year = 2026;

echo "--- KPI DIAGNOSTIC START ---\n";

$user = User::where('name', 'like', "%$userName%")->first();
if (!$user) {
    die("ERROR: User '$userName' not found.\n");
}
echo "Found User: {$user->name} (ID: {$user->id})\n";
echo "User Role: {$user->role}\n";

$employee = Employee::where('user_id', $user->id)->first();
echo "Found Employee: " . ($employee->nik ?? 'No NIK') . " (ID: " . ($employee->id ?? 'No ID') . ")\n";

$kpiService = new KpiService();

// Mocking the discovery process inside collectMusicWorkPoints
echo "\n--- SIMULATING TRACK DISCOVERY ---\n";

// Track E: Editing
$editingWorks = \App\Models\SoundEngineerEditing::where(function($q) use ($user) {
    $q->where('sound_engineer_id', $user->id)
      ->orWhere('created_by', $user->id)
      ->orWhereNull('sound_engineer_id');
})->get();

echo "Total potential SoundEngineerEditing records found: " . $editingWorks->count() . "\n";
foreach ($editingWorks as $ew) {
    echo " - Record ID: {$ew->id}, Episode ID: {$ew->episode_id}, SE ID: " . ($ew->sound_engineer_id ?? 'NULL') . ", Status: {$ew->status}\n";
    echo "   Created At: {$ew->created_at}, Submitted At: " . ($ew->submitted_at ?? 'NULL') . ", Updated At: {$ew->updated_at}\n";
    
    $episode = \App\Models\Episode::find($ew->episode_id);
    if ($episode) {
        echo "   Episode: {$episode->title} (Program ID: {$episode->program_id}), Air Date: " . ($episode->air_date ?? 'NULL') . "\n";
        
        // Check Team Membership if unassigned
        if (!$ew->sound_engineer_id) {
             $inTeam = \App\Models\Program::whereHas('teams.members', function($q) use ($user) {
                 $q->where('team_members.user_id', $user->id);
             })->where('id', $episode->program_id)->exists();
             echo "   Unassigned check: User in program team? " . ($inTeam ? 'YES' : 'NO') . "\n";
        }
    }
}

echo "\n--- RUNNING KPI CALCULATION ---\n";
$result = $kpiService->getEmployeeKpi($employee->id, $month, $year);

echo "KPI Result Summary:\n";
echo " - Total Points: " . $result['work_points']['total_points'] . "\n";
echo " - Max Points: " . $result['work_points']['max_points'] . "\n";
echo " - Percentage: " . $result['work_points']['percentage'] . "%\n";
echo " - Breakdown Count: " . count($result['work_points']['breakdown']) . "\n";

if (count($result['work_points']['breakdown']) > 0) {
    echo "\nBreakdown Details:\n";
    foreach ($result['work_points']['breakdown'] as $item) {
        echo " - [{$item['program_type']}] {$item['program_name']} - EP {$item['episode_number']}: {$item['role']} -> {$item['status']} ({$item['points']} pts)\n";
    }
} else {
    echo "Breakdown is EMPTY.\n";
}

echo "\n--- KPI DIAGNOSTIC END ---\n";
