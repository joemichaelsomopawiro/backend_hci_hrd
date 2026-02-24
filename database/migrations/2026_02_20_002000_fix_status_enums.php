<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix pr_editor_promosi_works status enum
        if (Schema::hasTable('pr_editor_promosi_works')) {
            DB::statement("ALTER TABLE `pr_editor_promosi_works` MODIFY COLUMN `status` ENUM('pending', 'waiting_editor', 'in_progress', 'pending_qc', 'needs_revision', 'completed') DEFAULT 'pending'");
        }

        // Fix pr_design_grafis_works status enum
        if (Schema::hasTable('pr_design_grafis_works')) {
            DB::statement("ALTER TABLE `pr_design_grafis_works` MODIFY COLUMN `status` ENUM('pending', 'in_progress', 'submitted', 'pending_qc', 'needs_revision', 'completed') DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert pr_editor_promosi_works
        if (Schema::hasTable('pr_editor_promosi_works')) {
            DB::statement("ALTER TABLE `pr_editor_promosi_works` MODIFY COLUMN `status` ENUM('pending', 'waiting_editor', 'in_progress', 'pending_qc', 'completed') DEFAULT 'pending'");
        }

        // Revert pr_design_grafis_works
        if (Schema::hasTable('pr_design_grafis_works')) {
            DB::statement("ALTER TABLE `pr_design_grafis_works` MODIFY COLUMN `status` ENUM('pending', 'in_progress', 'submitted', 'pending_qc', 'completed') DEFAULT 'pending'");
        }
    }
};
