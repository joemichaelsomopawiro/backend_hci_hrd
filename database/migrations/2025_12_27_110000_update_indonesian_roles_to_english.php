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
     * Update all Indonesian role names to English:
     * - "Produksi" -> "Production"
     * - "Promosi" -> "Promotion" (if exists)
     * - "Design Grafis" -> "Graphic Design" (already done, but ensure consistency)
     * - "Editor Promosi" -> "Editor Promotion" (if exists)
     * - "Kreatif" -> "Creative" (if exists)
     * - "Musik Arranger" -> "Music Arranger" (if exists)
     */
    public function up(): void
    {
        // Update users table
        DB::table('users')
            ->where('role', 'Produksi')
            ->update(['role' => 'Production']);
        
        DB::table('users')
            ->where('role', 'Promosi')
            ->update(['role' => 'Promotion']);
        
        DB::table('users')
            ->where('role', 'Design Grafis')
            ->update(['role' => 'Graphic Design']);
        
        DB::table('users')
            ->where('role', 'Editor Promosi')
            ->update(['role' => 'Editor Promotion']);
        
        DB::table('users')
            ->where('role', 'Kreatif')
            ->update(['role' => 'Creative']);
        
        DB::table('users')
            ->where('role', 'Musik Arranger')
            ->update(['role' => 'Music Arranger']);
        
        // Update employees table
        DB::table('employees')
            ->where('jabatan_saat_ini', 'Produksi')
            ->update(['jabatan_saat_ini' => 'Production']);
        
        DB::table('employees')
            ->where('jabatan_saat_ini', 'Promosi')
            ->update(['jabatan_saat_ini' => 'Promotion']);
        
        DB::table('employees')
            ->where('jabatan_saat_ini', 'Design Grafis')
            ->update(['jabatan_saat_ini' => 'Graphic Design']);
        
        DB::table('employees')
            ->where('jabatan_saat_ini', 'Editor Promosi')
            ->update(['jabatan_saat_ini' => 'Editor Promotion']);
        
        DB::table('employees')
            ->where('jabatan_saat_ini', 'Kreatif')
            ->update(['jabatan_saat_ini' => 'Creative']);
        
        DB::table('employees')
            ->where('jabatan_saat_ini', 'Musik Arranger')
            ->update(['jabatan_saat_ini' => 'Music Arranger']);
        
        // Update custom_roles table if exists
        if (Schema::hasTable('custom_roles')) {
            DB::table('custom_roles')
                ->where('role_name', 'Produksi')
                ->update(['role_name' => 'Production']);
            
            DB::table('custom_roles')
                ->where('role_name', 'Promosi')
                ->update(['role_name' => 'Promotion']);
            
            DB::table('custom_roles')
                ->where('role_name', 'Design Grafis')
                ->update(['role_name' => 'Graphic Design']);
            
            DB::table('custom_roles')
                ->where('role_name', 'Editor Promosi')
                ->update(['role_name' => 'Editor Promotion']);
            
            DB::table('custom_roles')
                ->where('role_name', 'Kreatif')
                ->update(['role_name' => 'Creative']);
            
            DB::table('custom_roles')
                ->where('role_name', 'Musik Arranger')
                ->update(['role_name' => 'Music Arranger']);
        }
    }

    /**
     * Reverse the migrations.
     * 
     * WARNING: Rollback is not recommended as we've standardized to English roles.
     * This method will attempt to revert, but may fail if enum values don't include Indonesian roles.
     */
    public function down(): void
    {
        // Get current enum values
        $usersEnumResult = DB::select("SHOW COLUMNS FROM users WHERE Field = 'role'");
        $employeesEnumResult = DB::select("SHOW COLUMNS FROM employees WHERE Field = 'jabatan_saat_ini'");
        
        $usersEnum = $usersEnumResult[0]->Type ?? '';
        $employeesEnum = $employeesEnumResult[0]->Type ?? '';
        
        // Only proceed if Indonesian roles exist in enum
        // For safety, we'll skip rollback if enum doesn't contain Indonesian values
        // This prevents the "Data truncated" error
        
        // Check if 'Produksi' exists in enum before attempting update
        if (strpos($usersEnum, 'Produksi') !== false) {
            DB::table('users')
                ->where('role', 'Production')
                ->update(['role' => 'Produksi']);
        }
        
        if (strpos($usersEnum, 'Promosi') !== false) {
            DB::table('users')
                ->where('role', 'Promotion')
                ->update(['role' => 'Promosi']);
        }
        
        if (strpos($usersEnum, 'Design Grafis') !== false) {
            DB::table('users')
                ->where('role', 'Graphic Design')
                ->update(['role' => 'Design Grafis']);
        }
        
        if (strpos($usersEnum, 'Editor Promosi') !== false) {
            DB::table('users')
                ->where('role', 'Editor Promotion')
                ->update(['role' => 'Editor Promosi']);
        }
        
        if (strpos($usersEnum, 'Kreatif') !== false) {
            DB::table('users')
                ->where('role', 'Creative')
                ->update(['role' => 'Kreatif']);
        }
        
        if (strpos($usersEnum, 'Musik Arranger') !== false) {
            DB::table('users')
                ->where('role', 'Music Arranger')
                ->update(['role' => 'Musik Arranger']);
        }
        
        // Update employees table
        if (strpos($employeesEnum, 'Produksi') !== false) {
            DB::table('employees')
                ->where('jabatan_saat_ini', 'Production')
                ->update(['jabatan_saat_ini' => 'Produksi']);
        }
        
        if (strpos($employeesEnum, 'Promosi') !== false) {
            DB::table('employees')
                ->where('jabatan_saat_ini', 'Promotion')
                ->update(['jabatan_saat_ini' => 'Promosi']);
        }
        
        if (strpos($employeesEnum, 'Design Grafis') !== false) {
            DB::table('employees')
                ->where('jabatan_saat_ini', 'Graphic Design')
                ->update(['jabatan_saat_ini' => 'Design Grafis']);
        }
        
        if (strpos($employeesEnum, 'Editor Promosi') !== false) {
            DB::table('employees')
                ->where('jabatan_saat_ini', 'Editor Promotion')
                ->update(['jabatan_saat_ini' => 'Editor Promosi']);
        }
        
        if (strpos($employeesEnum, 'Kreatif') !== false) {
            DB::table('employees')
                ->where('jabatan_saat_ini', 'Creative')
                ->update(['jabatan_saat_ini' => 'Kreatif']);
        }
        
        if (strpos($employeesEnum, 'Musik Arranger') !== false) {
            DB::table('employees')
                ->where('jabatan_saat_ini', 'Music Arranger')
                ->update(['jabatan_saat_ini' => 'Musik Arranger']);
        }
        
        // Update custom_roles table (no enum constraint, so safe to update)
        if (Schema::hasTable('custom_roles')) {
            DB::table('custom_roles')
                ->where('role_name', 'Production')
                ->update(['role_name' => 'Produksi']);
            
            DB::table('custom_roles')
                ->where('role_name', 'Promotion')
                ->update(['role_name' => 'Promosi']);
            
            DB::table('custom_roles')
                ->where('role_name', 'Graphic Design')
                ->update(['role_name' => 'Design Grafis']);
            
            DB::table('custom_roles')
                ->where('role_name', 'Editor Promotion')
                ->update(['role_name' => 'Editor Promosi']);
            
            DB::table('custom_roles')
                ->where('role_name', 'Creative')
                ->update(['role_name' => 'Kreatif']);
            
            DB::table('custom_roles')
                ->where('role_name', 'Music Arranger')
                ->update(['role_name' => 'Musik Arranger']);
        }
    }
};

