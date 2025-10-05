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
        // Update the enum to include all existing roles plus new ones
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('HR', 'Manager', 'Employee', 'GA', 'Singer', 'Creative', 'Producer', 'Social Media', 'Music Arranger', 'Program Manager', 'Hopeline Care', 'Distribution Manager', 'Sound Engineer', 'General Affairs') DEFAULT 'Employee'");
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