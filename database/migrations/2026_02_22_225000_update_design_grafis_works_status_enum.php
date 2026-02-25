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
        if (Schema::hasTable('design_grafis_works')) {
            // Using raw SQL because ENUM updates are tricky with standard Blueprint
            DB::statement("ALTER TABLE design_grafis_works MODIFY COLUMN status ENUM('draft', 'designing', 'in_progress', 'completed', 'reviewed', 'approved', 'revision_needed', 'rejected') NOT NULL DEFAULT 'draft'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('design_grafis_works')) {
            DB::statement("ALTER TABLE design_grafis_works MODIFY COLUMN status ENUM('draft', 'designing', 'completed', 'reviewed', 'approved') NOT NULL DEFAULT 'draft'");
        }
    }
};
