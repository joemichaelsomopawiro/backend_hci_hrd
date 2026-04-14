<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Employee;
use App\Models\PrProgram;
use App\Models\PrEpisode;
use App\Models\Program;
use App\Models\Episode;
use App\Models\PrCreativeWork;
use App\Models\PrProduksiWork;
use App\Models\MusicArrangement;
use App\Models\QualityControl;
use Carbon\Carbon;

class KpiTestSeeder extends Seeder
{
    public function run()
    {
        // 1. Ensure basic roles exist as testing candidates
        $rolesData = [
            ['name' => 'Test Creative', 'role' => 'Creative'],
            ['name' => 'Test Producer', 'role' => 'Producer'],
            ['name' => 'Test Music Arranger', 'role' => 'Music Arranger'],
            ['name' => 'Test Sound Engineer', 'role' => 'Sound Engineer'],
            ['name' => 'Test Manager', 'role' => 'Program Manager'],
        ];

        $users = [];
        foreach ($rolesData as $d) {
            // Check if user exists
            $user = User::where('role', $d['role'])->first();
            if (!$user) {
                // Must ensure employee exists
                $employee = Employee::create([
                    'nama_lengkap' => $d['name'],
                    'jabatan_saat_ini' => $d['role'],
                    'jenis_kelamin' => 'Laki-laki',
                    'email' => strtolower(str_replace(' ', '.', $d['name'])).'@hcindo.org',
                ]);

                $user = User::create([
                    'name' => $d['name'],
                    'email' => $employee->email,
                    'phone' => '08000'.$employee->id,
                    'password' => bcrypt('password'),
                    'role' => $d['role'],
                    'employee_id' => $employee->id
                ]);
            } else {
                // If user exists, make sure they have an employee
                if (!$user->employee_id) {
                    $employee = Employee::create([
                        'nama_lengkap' => $user->name,
                        'jabatan_saat_ini' => $user->role,
                        'jenis_kelamin' => 'Laki-laki',
                        'email' => $user->email,
                    ]);
                    $user->update(['employee_id' => $employee->id]);
                }
            }
            $users[$d['role']] = $user;
        }

        // ============================================
        // 2. CREATE PROGRAM REGULAR TEST EPISODES
        // ============================================
        $prProgram = PrProgram::first();
        if (!$prProgram) {
            $prProgram = PrProgram::create([
                'name' => 'KPI Test Regular Program',
                'description' => 'Program for KPI testing',
                'status' => 'draft',
                'program_year' => date('Y'),
                'target_audience' => 'All'
            ]);
        }

        $now = Carbon::now();
        
        // Create 3 Regular Episodes: On Time, Late, Overdue
        $scenarios = [
            ['suffix' => 'On Time', 'status' => 'on_time', 'days_offset' => -2],
            ['suffix' => 'Late', 'status' => 'late', 'days_offset' => +2],
            ['suffix' => 'Overdue', 'status' => 'overdue', 'days_offset' => +10],
        ];

        foreach ($scenarios as $idx => $scenario) {
            // Air date is 7 days from now just to give it a basis
            $airDate = $now->copy()->subDays($scenario['days_offset']);

            $prEpisode = PrEpisode::create([
                'program_id' => $prProgram->id,
                'episode_number' => rand(10000, 99999) + $idx,
                'title' => 'Test Episode ' . $scenario['suffix'],
                'air_date' => $airDate,
                'status' => 'scheduled',
            ]);

            // "Completion Time" based on scenario and WorkflowStep logic
            $deadlineCreativeDays = \App\Constants\WorkflowStep::getDeadlineDaysForRole('kreatif') ?? 14;
            $deadlineProduksiDays = \App\Constants\WorkflowStep::getDeadlineDaysForRole('produksi') ?? 10;
            
            $deadlineCreative = $airDate->copy()->subDays($deadlineCreativeDays);
            $deadlineProduksi = $airDate->copy()->subDays($deadlineProduksiDays);

            // If Overdue, don't create work completions. Let it be overdue without completion.
            if ($scenario['status'] === 'overdue') {
                continue; 
            }

            // Calculate "Completion Tool" based on scenario
            // For On Time, complete 1 day before deadline. For Late, complete 1 day after deadline.
            $compCreative = $scenario['status'] === 'on_time' ? $deadlineCreative->copy()->subDay() : $deadlineCreative->copy()->addDays(2);

            PrCreativeWork::create([
                'pr_episode_id' => $prEpisode->id,
                'status' => 'approved',
                'created_by' => $users['Creative']->id,
                'reviewed_at' => $compCreative,
            ]);
            
            $compProduksi = $scenario['status'] === 'on_time' ? $deadlineProduksi->copy()->subDay() : $deadlineProduksi->copy()->addDays(2);
            PrProduksiWork::create([
                'pr_episode_id' => $prEpisode->id,
                'status' => 'completed',
                'completed_by' => $users['Producer']->id,
                'completed_at' => $compProduksi,
            ]);
        }

        // ============================================
        // 3. CREATE PROGRAM MUSIC TEST EPISODES
        // ============================================
        $musicProgram = Program::where('name', 'like', '%music%')->first();
        if (!$musicProgram) {
            $musicProgram = Program::firstOrCreate(
                ['name' => 'KPI Test Music Program'],
                [
                    'description' => 'Music Program for KPI testing',
                    'status' => 'active'
                ]
            );
        }

        foreach ($scenarios as $idx => $scenario) {
            $airDate = $now->copy()->subDays($scenario['days_offset']);

            $musicEpisode = Episode::create([
                'program_id' => $musicProgram->id,
                'episode_number' => rand(10000, 99999) + $idx,
                'title' => 'Test Music ' . $scenario['suffix'],
                'air_date' => $airDate,
                'status' => 'in_production',
            ]);

            // Deadlines
            $deadlineMusicArrDays = \App\Constants\WorkflowStep::getDeadlineDaysForRole('music_arranger') ?? 8;
            $deadlineSoundEngDays = \App\Constants\WorkflowStep::getDeadlineDaysForRole('sound_engineer') ?? 8;
            
            $deadlineMusicArr = $airDate->copy()->subDays($deadlineMusicArrDays);
            $deadlineSoundEng = $airDate->copy()->subDays($deadlineSoundEngDays);

            if ($scenario['status'] === 'overdue') {
                continue;
            }

            $compArr = $scenario['status'] === 'on_time' ? $deadlineMusicArr->copy()->subDay() : $deadlineMusicArr->copy()->addDays(2);
            $compSE = $scenario['status'] === 'on_time' ? $deadlineSoundEng->copy()->subDay() : $deadlineSoundEng->copy()->addDays(2);

            MusicArrangement::create([
                'episode_id' => $musicEpisode->id,
                'song_title' => 'Song ' . $scenario['suffix'],
                'status' => 'approved',
                'created_by' => $users['Music Arranger']->id,
                'submitted_at' => $compArr,
                'needs_sound_engineer_help' => true,
                'sound_engineer_helper_id' => $users['Sound Engineer']->id,
                'sound_engineer_help_at' => $compSE,
            ]);
        }
    }
}
