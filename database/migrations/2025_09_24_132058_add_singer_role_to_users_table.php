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
        // Check if table exists
        if (!Schema::hasTable('users')) {
            return;
        }
        
        // Check if role column exists
        if (!Schema::hasColumn('users', 'role')) {
            return;
        }
        
        try {
            // Get current enum values
            $result = DB::select("SHOW COLUMNS FROM users WHERE Field = 'role'");
            if (!empty($result)) {
                $currentType = $result[0]->Type;
                // Check if 'Singer' is already in the enum
                if (strpos($currentType, 'Singer') === false) {
                    // Update the enum to include all existing roles plus new ones
                    DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('HR', 'Manager', 'Employee', 'GA', 'Singer', 'Creative', 'Producer', 'Social Media', 'Music Arranger', 'Program Manager', 'Hopeline Care', 'Distribution Manager', 'Sound Engineer', 'General Affairs') DEFAULT 'Employee'");
                }
            }
        } catch (\Exception $e) {
            // Enum change failed, skip
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('HR', 'Manager', 'Employee', 'GA') DEFAULT 'Employee'");
    }
};