<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Add 'promoted' and 'broadcasting_complete' statuses to pr_episodes and pr_programs
     */
    public function up(): void
    {
        // Add to pr_episodes
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
                'completed',
                'broadcasting_complete',
                'promoted'
            ) NOT NULL DEFAULT 'scheduled'
        ");

        // Add to pr_programs as well, since PrPromosiController updates it
        DB::statement("
            ALTER TABLE `pr_programs`
            MODIFY COLUMN `status` ENUM(
                'draft',
                'concept_pending',
                'concept_approved',
                'concept_rejected',
                'production_scheduled',
                'in_production',
                'editing',
                'submitted_to_manager',
                'manager_approved',
                'manager_rejected',
                'submitted_to_distribusi',
                'distribusi_approved',
                'distribusi_rejected',
                'scheduled',
                'distributed',
                'completed',
                'cancelled',
                'broadcasting_complete',
                'promoted'
            ) NOT NULL DEFAULT 'draft'
        ");
    }

    public function down(): void
    {
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

        DB::statement("
            ALTER TABLE `pr_programs`
            MODIFY COLUMN `status` ENUM(
                'draft',
                'concept_pending',
                'concept_approved',
                'concept_rejected',
                'production_scheduled',
                'in_production',
                'editing',
                'submitted_to_manager',
                'manager_approved',
                'manager_rejected',
                'submitted_to_distribusi',
                'distribusi_approved',
                'distribusi_rejected',
                'scheduled',
                'distributed',
                'completed',
                'cancelled'
            ) NOT NULL DEFAULT 'draft'
        ");
    }
};
