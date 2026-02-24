<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Tambah nilai status baru untuk alur broadcasting:
     * - step_6_completed : QC Manager Distribusi selesai, siap broadcasting
     * - broadcasting     : Sedang dalam proses broadcasting
     * - completed        : Episode selesai sepenuhnya (sudah dipublish)
     */
    public function up(): void
    {
        // MySQL/MariaDB: ubah definisi ENUM langsung via raw SQL
        DB::statement("
            ALTER TABLE `pr_episodes`
            MODIFY COLUMN `status` ENUM(
                'scheduled',
                'production',
                'editing',
                'ready_for_review',
                'manager_approved',
                'manager_rejected',
                'ready_for_distribusi',
                'distribusi_approved',
                'scheduled_to_air',
                'aired',
                'cancelled',
                'step_6_completed',
                'broadcasting',
                'completed'
            ) NOT NULL DEFAULT 'scheduled'
        ");
    }

    public function down(): void
    {
        // Kembalikan ke definisi ENUM semula
        DB::statement("
            ALTER TABLE `pr_episodes`
            MODIFY COLUMN `status` ENUM(
                'scheduled',
                'production',
                'editing',
                'ready_for_review',
                'manager_approved',
                'manager_rejected',
                'ready_for_distribusi',
                'distribusi_approved',
                'scheduled_to_air',
                'aired',
                'cancelled'
            ) NOT NULL DEFAULT 'scheduled'
        ");
    }
};
