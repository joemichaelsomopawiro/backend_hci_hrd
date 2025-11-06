<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanupMusicMigrations extends Command
{
    protected $signature = 'cleanup:music-migrations';
    protected $description = 'Hapus migrasi musik yang konflik tanpa mengganggu sistem HRD';

    public function handle()
    {
        $this->info('🧹 Membersihkan migrasi musik yang konflik...');
        
        try {
            // 1. Hapus foreign key constraints
            $this->info('1. Menghapus foreign key constraints...');
            $this->removeForeignKeys();
            
            // 2. Hapus tabel musik
            $this->info('2. Menghapus tabel musik...');
            $this->dropMusicTables();
            
            // 3. Hapus record migrasi musik
            $this->info('3. Menghapus record migrasi musik...');
            $this->removeMusicMigrationRecords();
            
            $this->info('✅ Pembersihan migrasi musik selesai!');
            $this->info('Sekarang Anda bisa menjalankan: php artisan migrate');
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    private function removeForeignKeys()
    {
        $tablesAndKeys = [
            'teams' => ['teams_program_id_foreign'],
            'program_team' => ['program_team_program_id_foreign', 'program_team_team_id_foreign'],
            'program_episodes' => ['program_episodes_program_id_foreign'],
            'episode_deadlines' => ['episode_deadlines_episode_id_foreign'],
            'program_proposals' => ['program_proposals_program_id_foreign'],
            'program_approvals' => ['program_approvals_program_id_foreign'],
            'budget_approvals' => ['budget_approvals_budget_id_foreign'],
            'production_teams_assignment' => ['production_teams_assignment_program_id_foreign', 'production_teams_assignment_team_id_foreign'],
            'general_affairs_budget_requests' => ['general_affairs_budget_requests_budget_id_foreign'],
            'promosi_bts' => ['promosi_bts_episode_id_foreign'],
            'produksi_equipment_requests' => ['produksi_equipment_requests_episode_id_foreign'],
            'shooting_run_sheets' => ['shooting_run_sheets_episode_id_foreign'],
            'sound_engineer_recordings' => ['sound_engineer_recordings_episode_id_foreign'],
            'art_set_properties' => ['art_set_properties_episode_id_foreign'],
            'editor_works' => ['editor_works_episode_id_foreign'],
            'design_grafis_works' => ['design_grafis_works_episode_id_foreign']
        ];
        
        foreach ($tablesAndKeys as $table => $keys) {
            if (Schema::hasTable($table)) {
                foreach ($keys as $key) {
                    try {
                        DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY IF EXISTS {$key}");
                        $this->line("   ✓ Dihapus FK: {$table}.{$key}");
                    } catch (\Exception $e) {
                        // Ignore if constraint doesn't exist
                    }
                }
            }
        }
    }
    
    private function dropMusicTables()
    {
        $musicTables = [
            'programs',
            'episodes', 
            'production_teams',
            'production_team_members',
            'creative_works',
            'budgets',
            'sound_engineer_recordings',
            'editor_works',
            'design_grafis_works',
            'media_files',
            'production_equipment',
            'teams',
            'program_team',
            'program_episodes',
            'episode_deadlines',
            'program_proposals',
            'program_approvals',
            'budget_approvals',
            'production_teams_assignment',
            'general_affairs_budget_requests',
            'promosi_bts',
            'produksi_equipment_requests',
            'shooting_run_sheets',
            'art_set_properties'
        ];
        
        foreach ($musicTables as $table) {
            if (Schema::hasTable($table)) {
                Schema::dropIfExists($table);
                $this->line("   ✓ Dihapus tabel: {$table}");
            }
        }
    }
    
    private function removeMusicMigrationRecords()
    {
        $musicMigrations = [
            '2025_10_05_143012_create_programs_table',
            '2025_10_05_143033_create_teams_table',
            '2025_10_05_143044_create_episodes_table',
            '2025_10_05_143055_create_schedules_table',
            '2025_10_05_143123_create_media_files_table',
            '2025_10_05_143149_create_production_equipment_table',
            '2025_10_05_143208_create_team_members_table',
            '2025_10_05_143217_create_program_team_table',
            '2025_10_05_143355_create_program_notifications_table',
            '2025_10_05_150000_add_workflow_fields_to_programs_table',
            '2025_10_05_150100_add_workflow_fields_to_episodes_table',
            '2025_10_05_150200_add_workflow_fields_to_schedules_table',
            '2025_10_09_000001_create_production_teams_table',
            '2025_10_09_000002_create_production_team_members_table',
            '2025_10_09_000003_create_program_regular_table',
            '2025_10_09_000004_create_program_episodes_table',
            '2025_10_09_000005_create_episode_deadlines_table',
            '2025_10_09_000006_create_program_proposals_table',
            '2025_10_09_000007_create_program_approvals_table',
            '2025_10_10_100001_create_creative_works_table',
            '2025_10_10_100002_create_budgets_table',
            '2025_10_10_100003_create_budget_approvals_table',
            '2025_10_10_100004_create_schedules_table',
            '2025_10_10_100005_create_production_teams_assignment_table',
            '2025_10_15_090706_create_general_affairs_budget_requests_table',
            '2025_10_15_090707_create_promosi_bts_table',
            '2025_10_15_090708_create_produksi_equipment_requests_table',
            '2025_10_15_090709_create_shooting_run_sheets_table',
            '2025_10_15_090710_create_sound_engineer_recordings_table',
            '2025_10_15_232832_create_art_set_properties_table',
            '2025_10_15_232850_create_editor_works_table',
            '2025_10_15_232906_create_design_grafis_works_table'
        ];
        
        foreach ($musicMigrations as $migration) {
            DB::table('migrations')->where('migration', $migration)->delete();
            $this->line("   ✓ Dihapus migrasi: {$migration}");
        }
    }
}
