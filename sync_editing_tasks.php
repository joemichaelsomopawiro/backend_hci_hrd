<?php

use App\Models\SoundEngineerRecording;
use App\Models\SoundEngineerEditing;
use App\Models\User;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 Starting Sound Engineer Editing Sync...\n";

$completedRecordings = SoundEngineerRecording::where('status', 'completed')->get();

foreach ($completedRecordings as $recording) {
    echo "Processing Recording ID: {$recording->id} (Episode: {$recording->episode_id})...\n";
    
    $existing = SoundEngineerEditing::where('sound_engineer_recording_id', $recording->id)->first();
    
    if (!$existing) {
        echo "⚠️ Missing Editing Task found! Creating for Recording ID: {$recording->id}...\n";
        
        $episode = $recording->episode;
        $program = $episode ? $episode->program : null;
        $productionTeam = ($program && $program->productionTeam) ? $program->productionTeam : null;
        
        $assignedSoundEngId = null;
        if ($productionTeam) {
            $seMember = $productionTeam->members()
                ->whereIn('role', ['sound_eng', 'sound_engineer', 'sound engineer', 'sound-engineer'])
                ->where('is_active', true)
                ->first();
            
            if ($seMember) {
                $assignedSoundEngId = $seMember->user_id;
            } else {
                $coordinator = \App\Models\ProductionTeamMember::whereHas('assignment', function($q) use ($recording) {
                    $q->where('episode_id', $recording->episode_id)
                      ->where('team_type', 'recording');
                })->where('is_coordinator', true)
                  ->where('is_active', true)
                  ->first();
                  
                if ($coordinator) {
                    $assignedSoundEngId = $coordinator->user_id;
                }
            }
        }

        $editing = SoundEngineerEditing::create([
            'episode_id' => $recording->episode_id,
            'sound_engineer_recording_id' => $recording->id,
            'sound_engineer_id' => $assignedSoundEngId,
            'vocal_file_path' => $recording->file_path ?? null,
            'vocal_file_link' => $recording->file_link,
            'editing_notes' => "Sync-fix: Recording oleh Tim Rekam Vokal. Notes: " . ($recording->recording_notes ?? 'N/A'),
            'status' => 'in_progress',
            'created_by' => $recording->created_by ?? 1
        ]);
        
        echo "✅ Created Editing Task ID: {$editing->id}\n";
    } else {
        echo "✅ Task already exists (Status: {$existing->status}).\n";
    }
}

echo "🏁 Sync Complete!\n";
