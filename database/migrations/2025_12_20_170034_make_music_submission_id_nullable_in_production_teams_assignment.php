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
     * Make music_submission_id nullable in production_teams_assignment table
     * This allows assignments without music submissions (for creative works)
     */
    public function up(): void
    {
        if (Schema::hasTable('production_teams_assignment') && 
            Schema::hasColumn('production_teams_assignment', 'music_submission_id')) {
            
            try {
                // Get foreign key constraint name
                $constraints = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'production_teams_assignment'
                    AND COLUMN_NAME = 'music_submission_id'
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                
                $fkName = null;
                if (!empty($constraints)) {
                    $fkName = $constraints[0]->CONSTRAINT_NAME;
                }
                
                // Drop foreign key if exists
                if ($fkName) {
                    DB::statement("ALTER TABLE production_teams_assignment DROP FOREIGN KEY `{$fkName}`");
                }
                
                // Modify column to be nullable
                DB::statement("ALTER TABLE production_teams_assignment MODIFY COLUMN music_submission_id BIGINT UNSIGNED NULL");
                
                // Recreate foreign key constraint with nullable
                if ($fkName) {
                    DB::statement("
                        ALTER TABLE production_teams_assignment 
                        ADD CONSTRAINT `{$fkName}` 
                        FOREIGN KEY (`music_submission_id`) 
                        REFERENCES `music_submissions` (`id`) 
                        ON DELETE CASCADE
                    ");
                }
            } catch (\Exception $e) {
                // If direct SQL fails, try using Schema
                Schema::table('production_teams_assignment', function (Blueprint $table) {
                    $table->foreignId('music_submission_id')->nullable()->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('production_teams_assignment') && 
            Schema::hasColumn('production_teams_assignment', 'music_submission_id')) {
            
            try {
                // Get foreign key constraint name
                $constraints = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'production_teams_assignment'
                    AND COLUMN_NAME = 'music_submission_id'
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                
                $fkName = null;
                if (!empty($constraints)) {
                    $fkName = $constraints[0]->CONSTRAINT_NAME;
                }
                
                // Drop foreign key if exists
                if ($fkName) {
                    DB::statement("ALTER TABLE production_teams_assignment DROP FOREIGN KEY `{$fkName}`");
                }
                
                // First, set all NULL values to a default (or we can't make it NOT NULL)
                // For safety, we'll only change if there are no NULL values
                $nullCount = DB::table('production_teams_assignment')
                    ->whereNull('music_submission_id')
                    ->count();
                
                if ($nullCount > 0) {
                    throw new \Exception("Cannot make music_submission_id NOT NULL: {$nullCount} rows have NULL values. Please update these rows first.");
                }
                
                // Modify column to be NOT NULL
                DB::statement("ALTER TABLE production_teams_assignment MODIFY COLUMN music_submission_id BIGINT UNSIGNED NOT NULL");
                
                // Recreate foreign key constraint
                if ($fkName) {
                    DB::statement("
                        ALTER TABLE production_teams_assignment 
                        ADD CONSTRAINT `{$fkName}` 
                        FOREIGN KEY (`music_submission_id`) 
                        REFERENCES `music_submissions` (`id`) 
                        ON DELETE CASCADE
                    ");
                }
            } catch (\Exception $e) {
                // If direct SQL fails, try using Schema
                Schema::table('production_teams_assignment', function (Blueprint $table) {
                    $table->foreignId('music_submission_id')->nullable(false)->change();
                });
            }
        }
    }
};
