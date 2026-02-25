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
        // Update pr_editor_works status
        DB::statement("ALTER TABLE pr_editor_works MODIFY COLUMN status ENUM('draft', 'editing', 'pending_review', 'approved', 'rejected', 'completed', 'revision_requested') DEFAULT 'draft'");

        // Update pr_produksi_works status
        // Correct list based on 2026_02_05_120000_add_finished_shooting_to_pr_produksi_works_status.php
        DB::statement("ALTER TABLE pr_produksi_works MODIFY COLUMN status ENUM('pending', 'in_progress', 'equipment_requested', 'ready_to_shoot', 'shooting', 'completed', 'finished_shooting', 'revision_requested') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert pr_editor_works
        DB::statement("ALTER TABLE pr_editor_works MODIFY COLUMN status ENUM('draft', 'editing', 'pending_review', 'approved', 'rejected', 'completed') DEFAULT 'draft'");

        // Revert pr_produksi_works
        DB::statement("ALTER TABLE pr_produksi_works MODIFY COLUMN status ENUM('pending', 'in_progress', 'equipment_requested', 'ready_to_shoot', 'shooting', 'completed', 'finished_shooting') DEFAULT 'pending'");
    }
};
