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
     * Update role enum to support both ProductionTeam roles and Assignment roles
     */
    public function up(): void
    {
        if (Schema::hasTable('production_team_members') && Schema::hasColumn('production_team_members', 'role')) {
            try {
                // Get current enum values to check what we have
                $result = DB::select("SHOW COLUMNS FROM production_team_members WHERE Field = 'role'");
                
                if (!empty($result)) {
                    $currentType = $result[0]->Type;
                    
                    // Check if assignment roles are already in the enum
                    if (strpos($currentType, 'leader') === false) {
                        // Update enum to include all roles: ProductionTeam roles + Assignment roles
                        DB::statement("ALTER TABLE production_team_members MODIFY COLUMN role ENUM(
                            'kreatif',
                            'musik_arr',
                            'sound_eng',
                            'produksi',
                            'editor',
                            'art_set_design',
                            'leader',
                            'crew',
                            'talent',
                            'support'
                        )");
                    }
                }
            } catch (\Exception $e) {
                // If enum modification fails, try changing to VARCHAR for more flexibility
                // This is a fallback if enum modification doesn't work
                try {
                    DB::statement("ALTER TABLE production_team_members MODIFY COLUMN role VARCHAR(50)");
                } catch (\Exception $e2) {
                    // Ignore if this also fails
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('production_team_members') && Schema::hasColumn('production_team_members', 'role')) {
            try {
                // Revert to original ProductionTeam roles only
                DB::statement("ALTER TABLE production_team_members MODIFY COLUMN role ENUM(
                    'kreatif',
                    'musik_arr',
                    'sound_eng',
                    'produksi',
                    'editor',
                    'art_set_design'
                )");
            } catch (\Exception $e) {
                // Ignore if fails
            }
        }
    }
};
