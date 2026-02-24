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
        // Add pending_qc status to pr_editor_promosi_works
        DB::statement("ALTER TABLE pr_editor_promosi_works MODIFY COLUMN status ENUM('pending', 'waiting_editor', 'in_progress', 'pending_qc', 'completed') DEFAULT 'pending'");

        // Add submitted_at and deadline fields
        Schema::table('pr_editor_promosi_works', function (Blueprint $table) {
            if (!Schema::hasColumn('pr_editor_promosi_works', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('pr_editor_promosi_works', 'deadline')) {
                $table->date('deadline')->nullable()->after('submitted_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove pending_qc status
        DB::statement("ALTER TABLE pr_editor_promosi_works MODIFY COLUMN status ENUM('pending', 'waiting_editor', 'in_progress', 'completed') DEFAULT 'pending'");

        // Drop the new fields
        Schema::table('pr_editor_promosi_works', function (Blueprint $table) {
            if (Schema::hasColumn('pr_editor_promosi_works', 'submitted_at')) {
                $table->dropColumn('submitted_at');
            }
            if (Schema::hasColumn('pr_editor_promosi_works', 'deadline')) {
                $table->dropColumn('deadline');
            }
        });
    }
};
