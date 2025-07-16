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
        // Update enum operation untuk menambahkan 'pull_current_month_data'
        DB::statement("ALTER TABLE attendance_sync_logs MODIFY COLUMN operation ENUM(
            'pull_data',
            'pull_today_data',
            'pull_current_month_data',
            'pull_user_data',
            'push_user',
            'delete_user',
            'clear_data',
            'sync_time',
            'restart_machine',
            'test_connection'
        ) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan enum operation ke versi sebelumnya
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
        ) NOT NULL");
    }
}; 