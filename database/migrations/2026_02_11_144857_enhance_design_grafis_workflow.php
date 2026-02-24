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
        // First, update status enum from 'completed' to 'submitted'
        // Using raw SQL because Laravel doesn't support modifying enum values directly
        DB::statement("ALTER TABLE pr_design_grafis_works MODIFY COLUMN status ENUM('pending', 'in_progress', 'submitted') NOT NULL DEFAULT 'pending'");

        // Update existing 'completed' statuses to 'submitted'
        DB::table('pr_design_grafis_works')
            ->where('status', 'completed')
            ->update(['status' => 'submitted']);

        Schema::table('pr_design_grafis_works', function (Blueprint $table) {
            // Add submitted_at column (copy data from completed_at if it exists)
            if (!Schema::hasColumn('pr_design_grafis_works', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('started_at');
            }

            // Add deadline column
            if (!Schema::hasColumn('pr_design_grafis_works', 'deadline')) {
                $table->datetime('deadline')->nullable()->after('notes');
            }
        });

        // Copy completed_at data to submitted_at
        if (Schema::hasColumn('pr_design_grafis_works', 'completed_at')) {
            DB::statement('UPDATE pr_design_grafis_works SET submitted_at = completed_at WHERE completed_at IS NOT NULL');

            // Now drop completed_at
            Schema::table('pr_design_grafis_works', function (Blueprint $table) {
                $table->dropColumn('completed_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pr_design_grafis_works', function (Blueprint $table) {
            // Add back completed_at
            if (!Schema::hasColumn('pr_design_grafis_works', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('started_at');
            }
        });

        // Copy submitted_at data back to completed_at
        if (Schema::hasColumn('pr_design_grafis_works', 'submitted_at')) {
            DB::statement('UPDATE pr_design_grafis_works SET completed_at = submitted_at WHERE submitted_at IS NOT NULL');

            // Drop submitted_at
            Schema::table('pr_design_grafis_works', function (Blueprint $table) {
                $table->dropColumn('submitted_at');
            });
        }

        Schema::table('pr_design_grafis_works', function (Blueprint $table) {
            // Remove deadline column
            if (Schema::hasColumn('pr_design_grafis_works', 'deadline')) {
                $table->dropColumn('deadline');
            }
        });

        // Revert status enum back to original
        DB::statement("ALTER TABLE pr_design_grafis_works MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed') NOT NULL DEFAULT 'pending'");

        // Revert 'submitted' statuses back to 'completed'
        DB::table('pr_design_grafis_works')
            ->where('status', 'submitted')
            ->update(['status' => 'completed']);
    }
};
