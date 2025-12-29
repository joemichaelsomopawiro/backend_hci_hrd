<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update users table - change "Design Grafis" to "Graphic Design"
        DB::table('users')
            ->where('role', 'Design Grafis')
            ->update(['role' => 'Graphic Design']);
        
        // Update employees table - change "Design Grafis" to "Graphic Design"
        DB::table('employees')
            ->where('jabatan_saat_ini', 'Design Grafis')
            ->update(['jabatan_saat_ini' => 'Graphic Design']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to "Design Grafis"
        DB::table('users')
            ->where('role', 'Graphic Design')
            ->update(['role' => 'Design Grafis']);
        
        DB::table('employees')
            ->where('jabatan_saat_ini', 'Graphic Design')
            ->update(['jabatan_saat_ini' => 'Design Grafis']);
    }
};

