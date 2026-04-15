<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Program;
use App\Models\Deadline;
use App\Models\Episode;
use App\Constants\WorkflowStep;
use Carbon\Carbon;

$musicCategories = ['Musik', 'Music', 'musik'];
$programs = Program::whereIn('category', $musicCategories)->get();

foreach ($programs as $program) {
    echo "Updating program: {$program->name} (ID: {$program->id})\n";
    
    // Process ALL episodes for this program
    $episodes = $program->episodes()->get();
        
    foreach ($episodes as $episode) {
        echo "  Episode: {$episode->title} (ID: {$episode->id})\n";
        
        $rolesToAdd = [
            'program_manager', 'manager_distribusi', 'producer', 'musik_arr', 'sound_eng', 
            'kreatif', 'promotion', 'tim_setting_coord', 'tim_syuting_coord', 
            'tim_vocal_coord', 'general_affairs', 'art_set_design', 'editor', 
            'design_grafis', 'editor_promosi', 'quality_control', 'broadcasting'
        ];
        
        foreach ($rolesToAdd as $role) {
            $exists = Deadline::where('episode_id', $episode->id)->where('role', $role)->exists();
            if (!$exists) {
                Deadline::create([
                    'episode_id' => $episode->id,
                    'role' => $role,
                    'deadline_date' => $episode->air_date->copy()->subDays(WorkflowStep::getDeadlineDaysForRole($role, 'Musik')),
                    'description' => 'Auto-generated for full role reassignment',
                    'auto_generated' => true,
                    'created_by' => 1,
                    'status' => 'pending'
                ]);
                echo "    + Added deadline: {$role}\n";
            }
        }
    }
}

echo "Backfill completed successfully.\n";
