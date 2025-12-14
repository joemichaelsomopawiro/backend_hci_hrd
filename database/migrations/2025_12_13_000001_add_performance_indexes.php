<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add performance indexes for frequently queried columns
     */
    public function up(): void
    {
        // Programs table - add composite indexes for common queries
        if (Schema::hasTable('programs')) {
            Schema::table('programs', function (Blueprint $table) {
                // Composite index for status + created_at (for sorting)
                if (!$this->indexExists('programs', 'programs_status_created_at_index')) {
                    $table->index(['status', 'created_at'], 'programs_status_created_at_index');
                }
                
                // Composite index for manager_program_id + status
                if (!$this->indexExists('programs', 'programs_manager_status_index')) {
                    $table->index(['manager_program_id', 'status'], 'programs_manager_status_index');
                }
            });
        }
        
        // Episodes table - add composite indexes for common queries
        if (Schema::hasTable('episodes')) {
            Schema::table('episodes', function (Blueprint $table) {
                // Composite index for program_id + status
                if (!$this->indexExists('episodes', 'episodes_program_status_index')) {
                    $table->index(['program_id', 'status'], 'episodes_program_status_index');
                }
                
                // Composite index for program_id + current_workflow_state
                if (!$this->indexExists('episodes', 'episodes_program_workflow_index')) {
                    $table->index(['program_id', 'current_workflow_state'], 'episodes_program_workflow_index');
                }
                
                // Composite index for assigned_to_user + status
                if (!$this->indexExists('episodes', 'episodes_assigned_status_index')) {
                    $table->index(['assigned_to_user', 'status'], 'episodes_assigned_status_index');
                }
            });
        }
        
        // Production teams table - add composite indexes
        if (Schema::hasTable('production_teams')) {
            Schema::table('production_teams', function (Blueprint $table) {
                // Composite index for producer_id + is_active
                if (!$this->indexExists('production_teams', 'production_teams_producer_active_index')) {
                    $table->index(['producer_id', 'is_active'], 'production_teams_producer_active_index');
                }
                
                // Composite index for is_active + created_at (for sorting)
                if (!$this->indexExists('production_teams', 'production_teams_active_created_index')) {
                    $table->index(['is_active', 'created_at'], 'production_teams_active_created_index');
                }
            });
        }
        
        // Production team members table - add indexes if exists
        if (Schema::hasTable('production_team_members')) {
            Schema::table('production_team_members', function (Blueprint $table) {
                // Composite index for production_team_id + is_active
                if (!$this->indexExists('production_team_members', 'production_team_members_team_active_index')) {
                    $table->index(['production_team_id', 'is_active'], 'production_team_members_team_active_index');
                }
                
                // Composite index for user_id + role + is_active
                if (!$this->indexExists('production_team_members', 'production_team_members_user_role_active_index')) {
                    $table->index(['user_id', 'role', 'is_active'], 'production_team_members_user_role_active_index');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('programs')) {
            Schema::table('programs', function (Blueprint $table) {
                $table->dropIndex('programs_status_created_at_index');
                $table->dropIndex('programs_manager_status_index');
            });
        }
        
        if (Schema::hasTable('episodes')) {
            Schema::table('episodes', function (Blueprint $table) {
                $table->dropIndex('episodes_program_status_index');
                $table->dropIndex('episodes_program_workflow_index');
                $table->dropIndex('episodes_assigned_status_index');
            });
        }
        
        if (Schema::hasTable('production_teams')) {
            Schema::table('production_teams', function (Blueprint $table) {
                $table->dropIndex('production_teams_producer_active_index');
                $table->dropIndex('production_teams_active_created_index');
            });
        }
        
        if (Schema::hasTable('production_team_members')) {
            Schema::table('production_team_members', function (Blueprint $table) {
                $table->dropIndex('production_team_members_team_active_index');
                $table->dropIndex('production_team_members_user_role_active_index');
            });
        }
    }
    
    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        
        $result = $connection->select(
            "SELECT COUNT(*) as count FROM information_schema.statistics 
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$database, $table, $indexName]
        );
        
        return $result[0]->count > 0;
    }
};

