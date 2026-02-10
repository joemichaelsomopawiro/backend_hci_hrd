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
        // Add 'finished_shooting' to the status enum
        // Since Laravel doesn't support modifying enums directly in a database-agnostic way for all drivers,
        // and we are likely on MySQL/MariaDB given the XAMPP path:
        DB::statement("ALTER TABLE pr_produksi_works MODIFY COLUMN status ENUM('pending', 'in_progress', 'equipment_requested', 'ready_to_shoot', 'shooting', 'completed', 'finished_shooting') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum
        DB::statement("ALTER TABLE pr_produksi_works MODIFY COLUMN status ENUM('pending', 'in_progress', 'equipment_requested', 'ready_to_shoot', 'shooting', 'completed') NOT NULL DEFAULT 'pending'");
    }
};
