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
        // Add pending_qc and needs_revision to status enum
        DB::statement("ALTER TABLE pr_editor_works MODIFY COLUMN status ENUM('draft', 'editing', 'pending_review', 'approved', 'rejected', 'completed', 'revision_requested', 'revised', 'pending_qc', 'needs_revision') DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert status enum removing needs_revision
        DB::statement("ALTER TABLE pr_editor_works MODIFY COLUMN status ENUM('draft', 'editing', 'pending_review', 'approved', 'rejected', 'completed', 'revision_requested', 'revised', 'pending_qc') DEFAULT 'draft'");
    }
};
