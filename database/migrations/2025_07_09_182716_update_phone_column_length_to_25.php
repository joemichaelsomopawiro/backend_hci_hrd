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
        // Drop existing unique constraint first
        try {
            DB::statement('ALTER TABLE users DROP INDEX users_phone_unique');
        } catch (\Exception $e) {
            // Index doesn't exist, continue
        }

        Schema::table('users', function (Blueprint $table) {
            // Update phone column to support up to 25 characters for longer phone numbers
            $table->string('phone', 25)->change();
        });

        // Add unique constraint back
        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone', 'users_phone_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop unique constraint
        try {
            DB::statement('ALTER TABLE users DROP INDEX users_phone_unique');
        } catch (\Exception $e) {
            // Index doesn't exist, continue
        }

        Schema::table('users', function (Blueprint $table) {
            // Revert back to 20 characters
            $table->string('phone', 20)->change();
        });

        // Add unique constraint back
        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone', 'users_phone_unique');
        });
    }
};
