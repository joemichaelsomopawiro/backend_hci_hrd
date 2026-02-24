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
        // Update pr_editor_revision_notes status
        // Current: 'pending', 'approved_by_producer', 'sent_to_production'
        // New: 'pending', 'approved_by_producer', 'sent_to_production', 'revision_requested'
        DB::statement("ALTER TABLE pr_editor_revision_notes MODIFY COLUMN status ENUM('pending', 'approved_by_producer', 'sent_to_production', 'revision_requested') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert pr_editor_revision_notes
        DB::statement("ALTER TABLE pr_editor_revision_notes MODIFY COLUMN status ENUM('pending', 'approved_by_producer', 'sent_to_production') DEFAULT 'pending'");
    }
};
