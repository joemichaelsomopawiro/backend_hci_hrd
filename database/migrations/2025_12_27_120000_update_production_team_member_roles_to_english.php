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
     * Update Production Team Member roles from Indonesian to English:
     * - "kreatif" -> "creative"
     * - "produksi" -> "production"
     * - "design_grafis" -> "graphic_design" (if exists)
     * - "promosi" -> "promotion" (if exists)
     */
    public function up(): void
    {
        if (!Schema::hasTable('production_team_members')) {
            return;
        }

        // FIRST: Update enum values to include English roles
        // THEN: Update data
        
        // Step 1: Update enum values first (add English roles to enum)
        try {
            // Get current enum values
            $result = DB::select("SHOW COLUMNS FROM production_team_members WHERE Field = 'role'");
            
            if (!empty($result)) {
                $currentType = $result[0]->Type;
                
                // Check if enum needs updating
                $needsUpdate = false;
                $newEnumValues = [];
                
                // Parse current enum values
                if (preg_match("/enum\((.*)\)/", $currentType, $matches)) {
                    $enumValues = str_replace("'", "", $matches[1]);
                    $enumArray = explode(',', $enumValues);
                    
                    foreach ($enumArray as $value) {
                        $value = trim($value);
                        // Keep existing values, but add English versions
                        $newEnumValues[] = $value;
                        
                        // Add English equivalents if Indonesian exists
                        if ($value === 'kreatif' && !in_array('creative', $newEnumValues)) {
                            $newEnumValues[] = 'creative';
                            $needsUpdate = true;
                        }
                        if ($value === 'produksi' && !in_array('production', $newEnumValues)) {
                            $newEnumValues[] = 'production';
                            $needsUpdate = true;
                        }
                        if ($value === 'design_grafis' && !in_array('graphic_design', $newEnumValues)) {
                            $newEnumValues[] = 'graphic_design';
                            $needsUpdate = true;
                        }
                        if ($value === 'promosi' && !in_array('promotion', $newEnumValues)) {
                            $newEnumValues[] = 'promotion';
                            $needsUpdate = true;
                        }
                    }
                    
                    if ($needsUpdate) {
                        // Remove duplicates and sort
                        $newEnumValues = array_unique($newEnumValues);
                        sort($newEnumValues);
                        
                        // Format for SQL
                        $enumString = "'" . implode("', '", $newEnumValues) . "'";
                        
                        // Update enum to include both Indonesian and English
                        DB::statement("ALTER TABLE production_team_members MODIFY COLUMN role ENUM({$enumString})");
                    }
                }
            }
        } catch (\Exception $e) {
            // If enum modification fails, try changing to VARCHAR for more flexibility
            try {
                DB::statement("ALTER TABLE production_team_members MODIFY COLUMN role VARCHAR(50)");
            } catch (\Exception $e2) {
                // Ignore if this also fails
            }
        }
        
        // Step 2: Now update data from Indonesian to English
        DB::table('production_team_members')
            ->where('role', 'kreatif')
            ->update(['role' => 'creative']);
        
        DB::table('production_team_members')
            ->where('role', 'produksi')
            ->update(['role' => 'production']);
        
        // Check if design_grafis exists before updating
        $hasDesignGrafis = DB::table('production_team_members')
            ->where('role', 'design_grafis')
            ->exists();
        
        if ($hasDesignGrafis) {
            DB::table('production_team_members')
                ->where('role', 'design_grafis')
                ->update(['role' => 'graphic_design']);
        }
        
        // Check if promosi exists before updating
        $hasPromosi = DB::table('production_team_members')
            ->where('role', 'promosi')
            ->exists();
        
        if ($hasPromosi) {
            DB::table('production_team_members')
                ->where('role', 'promosi')
                ->update(['role' => 'promotion']);
        }
        
        // Step 3: Remove Indonesian values from enum (optional, for cleanup)
        try {
            $result = DB::select("SHOW COLUMNS FROM production_team_members WHERE Field = 'role'");
            
            if (!empty($result)) {
                $currentType = $result[0]->Type;
                
                if (preg_match("/enum\((.*)\)/", $currentType, $matches)) {
                    $enumValues = str_replace("'", "", $matches[1]);
                    $enumArray = explode(',', $enumValues);
                    
                    // Remove Indonesian values
                    $cleanEnumValues = array_filter($enumArray, function($value) {
                        $value = trim($value);
                        return !in_array($value, ['kreatif', 'produksi', 'design_grafis', 'promosi']);
                    });
                    
                    if (count($cleanEnumValues) < count($enumArray)) {
                        $cleanEnumValues = array_values($cleanEnumValues);
                        $enumString = "'" . implode("', '", $cleanEnumValues) . "'";
                        DB::statement("ALTER TABLE production_team_members MODIFY COLUMN role ENUM({$enumString})");
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore if cleanup fails
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('production_team_members')) {
            return;
        }

        // Revert back to Indonesian
        DB::table('production_team_members')
            ->where('role', 'creative')
            ->update(['role' => 'kreatif']);
        
        DB::table('production_team_members')
            ->where('role', 'production')
            ->update(['role' => 'produksi']);
        
        DB::table('production_team_members')
            ->where('role', 'graphic_design')
            ->update(['role' => 'design_grafis']);
        
        DB::table('production_team_members')
            ->where('role', 'promotion')
            ->update(['role' => 'promosi']);
    }
};

