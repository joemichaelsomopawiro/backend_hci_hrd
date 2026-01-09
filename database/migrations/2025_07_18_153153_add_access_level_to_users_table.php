<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
        
        // Only add column if it doesn't exist
        if (!Schema::hasColumn('users', 'access_level')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('access_level', ['employee', 'manager', 'hr_readonly', 'hr_full', 'director'])->default('employee')->after('role');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('access_level');
        });
    }
};
