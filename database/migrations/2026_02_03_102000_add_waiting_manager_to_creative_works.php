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
        // Modify ENUM column by raw SQL as Doctrine DBAL doesn't support changing ENUM values easily
        // We include 'waiting_manager_approval' and also 'in_progress' which was added in previous migration to be safe
        DB::statement("ALTER TABLE creative_works MODIFY COLUMN status ENUM('draft', 'in_progress', 'submitted', 'approved', 'rejected', 'revised', 'waiting_manager_approval') DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to previous state (without waiting_manager_approval)
        // Be careful not to lose data if there are records with 'waiting_manager_approval'
        DB::statement("UPDATE creative_works SET status = 'submitted' WHERE status = 'waiting_manager_approval'");
        DB::statement("ALTER TABLE creative_works MODIFY COLUMN status ENUM('draft', 'in_progress', 'submitted', 'approved', 'rejected', 'revised') DEFAULT 'draft'");
    }
};
