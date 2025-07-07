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
        // Update enum to add 'pull_today_data'
        DB::statement("ALTER TABLE attendance_sync_logs MODIFY COLUMN operation ENUM(
            'pull_data',
            'pull_today_data',
            'pull_user_data',
            'push_user',
            'delete_user',
            'clear_data',
            'sync_time',
            'restart_machine',
            'test_connection'
        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert enum to original values
        DB::statement("ALTER TABLE attendance_sync_logs MODIFY COLUMN operation ENUM(
            'pull_data',
            'pull_user_data',
            'push_user',
            'delete_user',
            'clear_data',
            'sync_time',
            'restart_machine',
            'test_connection'
        )");
    }
}; 