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
        if (!Schema::hasTable('attendance_sync_logs')) {
            return;
        }
        
        // Check if column exists
        if (!Schema::hasColumn('attendance_sync_logs', 'operation')) {
            return;
        }
        
        try {
            // Get current enum values
            $result = DB::select("SHOW COLUMNS FROM attendance_sync_logs WHERE Field = 'operation'");
            if (!empty($result)) {
                $currentType = $result[0]->Type;
                // Check if 'pull_current_month_data' is already in the enum
                if (strpos($currentType, 'pull_current_month_data') === false) {
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