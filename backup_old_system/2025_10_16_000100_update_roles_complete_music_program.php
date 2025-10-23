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
        // Update enum role di users table untuk menambah semua role Music Program
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
            'HR', 'Program Manager', 'Distribution Manager', 'GA',
            'Finance', 'General Affairs', 'Office Assistant',
            'Producer', 'Creative', 'Production', 'Editor',
            'Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care',
            'VP President', 'President Director', 'Music Arranger',
            'Sound Engineer', 'Quality Control', 'Art & Set Design',
            'Editor Promotion', 'Employee'
        ) DEFAULT 'Employee'");
        
        // Update enum jabatan_saat_ini di employees table
        DB::statement("ALTER TABLE employees MODIFY COLUMN jabatan_saat_ini ENUM(
            'HR', 'Program Manager', 'Distribution Manager', 'GA',
            'Finance', 'General Affairs', 'Office Assistant',
            'Producer', 'Creative', 'Production', 'Editor',
            'Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care',
            'VP President', 'President Director', 'Music Arranger',
            'Sound Engineer', 'Quality Control', 'Art & Set Design',
            'Editor Promotion', 'Employee'
        ) DEFAULT 'Employee'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to previous enum values
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
            'HR', 'Program Manager', 'Distribution Manager', 'GA',
            'Finance', 'General Affairs', 'Office Assistant',
            'Producer', 'Creative', 'Production', 'Editor',
            'Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care',
            'VP President', 'President Director',
            'Employee'
        ) DEFAULT 'Employee'");
        
        DB::statement("ALTER TABLE employees MODIFY COLUMN jabatan_saat_ini ENUM(
            'HR', 'Program Manager', 'Distribution Manager', 'GA',
            'Finance', 'General Affairs', 'Office Assistant',
            'Producer', 'Creative', 'Production', 'Editor',
            'Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care',
            'VP President', 'President Director',
            'Employee'
        ) DEFAULT 'Employee'");
    }
};
