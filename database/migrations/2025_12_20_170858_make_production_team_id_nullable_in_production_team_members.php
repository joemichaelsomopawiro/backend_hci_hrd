<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Make production_team_id nullable in production_team_members table
     * This allows the table to support both:
     * 1. ProductionTeam members (with production_team_id)
     * 2. ProductionTeamAssignment members (with assignment_id, production_team_id = null)
     */
    public function up(): void
    {
        if (Schema::hasTable('production_team_members') && 
            Schema::hasColumn('production_team_members', 'production_team_id')) {
            
            try {
                // Get foreign key constraint name
                $constraints = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'production_team_members'
                    AND COLUMN_NAME = 'production_team_id'
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                
                $fkName = null;
                if (!empty($constraints)) {
                    $fkName = $constraints[0]->CONSTRAINT_NAME;
                }
                
                // Drop foreign key if exists
                if ($fkName) {
                    DB::statement("ALTER TABLE production_team_members DROP FOREIGN KEY `{$fkName}`");
                }
                
                // Modify column to be nullable
                DB::statement("ALTER TABLE production_team_members MODIFY COLUMN production_team_id BIGINT UNSIGNED NULL");
                
                // Recreate foreign key constraint with nullable
                if ($fkName) {
                    DB::statement("
                        ALTER TABLE production_team_members 
                        ADD CONSTRAINT `{$fkName}` 
                        FOREIGN KEY (`production_team_id`) 
                        REFERENCES `production_teams` (`id`) 
                        ON DELETE CASCADE
                    ");
                }
            } catch (\Exception $e) {
                // If direct SQL fails, try using Schema
                try {
                    Schema::table('production_team_members', function (Blueprint $table) {
                        $table->foreignId('production_team_id')->nullable()->change();
                    });
                } catch (\Exception $e2) {
                    // Log error but don't fail migration
                    \Log::warning('Failed to make production_team_id nullable', [
                        'error' => $e2->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('production_team_members') && 
            Schema::hasColumn('production_team_members', 'production_team_id')) {
            
            try {
                // Get foreign key constraint name
                $constraints = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'production_team_members'
                    AND COLUMN_NAME = 'production_team_id'
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                
                $fkName = null;
                if (!empty($constraints)) {
                    $fkName = $constraints[0]->CONSTRAINT_NAME;
                }
                
                // Drop foreign key if exists
                if ($fkName) {
                    DB::statement("ALTER TABLE production_team_members DROP FOREIGN KEY `{$fkName}`");
                }
                
                // First, check if there are NULL values
                $nullCount = DB::table('production_team_members')
                    ->whereNull('production_team_id')
                    ->count();
                
                if ($nullCount > 0) {
                    throw new \Exception("Cannot make production_team_id NOT NULL: {$nullCount} rows have NULL values. Please update these rows first.");
                }
                
                // Modify column to be NOT NULL
                DB::statement("ALTER TABLE production_team_members MODIFY COLUMN production_team_id BIGINT UNSIGNED NOT NULL");
                
                // Recreate foreign key constraint
                if ($fkName) {
                    DB::statement("
                        ALTER TABLE production_team_members 
                        ADD CONSTRAINT `{$fkName}` 
                        FOREIGN KEY (`production_team_id`) 
                        REFERENCES `production_teams` (`id`) 
                        ON DELETE CASCADE
                    ");
                }
            } catch (\Exception $e) {
                // If direct SQL fails, try using Schema
                try {
                    Schema::table('production_team_members', function (Blueprint $table) {
                        $table->foreignId('production_team_id')->nullable(false)->change();
                    });
                } catch (\Exception $e2) {
                    // Log error
                    \Log::warning('Failed to make production_team_id NOT NULL', [
                        'error' => $e2->getMessage()
                    ]);
                }
            }
        }
    }
};
