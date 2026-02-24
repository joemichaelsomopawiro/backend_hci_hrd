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
        // Update pr_editor_works status to add 'revised'
        DB::statement("ALTER TABLE pr_editor_works MODIFY COLUMN status ENUM('draft', 'editing', 'pending_review', 'approved', 'rejected', 'completed', 'revision_requested', 'revised') DEFAULT 'draft'");

        // Update pr_editor_revision_notes status to add 'revised' as well
        DB::statement("ALTER TABLE pr_editor_revision_notes MODIFY COLUMN status ENUM('revision_requested', 'revised', 'approved', 'rejected') DEFAULT 'revision_requested'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert pr_editor_works
        DB::statement("ALTER TABLE pr_editor_works MODIFY COLUMN status ENUM('draft', 'editing', 'pending_review', 'approved', 'rejected', 'completed', 'revision_requested') DEFAULT 'draft'");

        // Revert pr_editor_revision_notes
        DB::statement("ALTER TABLE pr_editor_revision_notes MODIFY COLUMN status ENUM('revision_requested', 'approved', 'rejected') DEFAULT 'revision_requested'");
    }
};
