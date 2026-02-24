<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add pending_qc to status enum for the correct table pr_design_grafis_works
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE pr_design_grafis_works MODIFY COLUMN status ENUM('pending', 'in_progress', 'submitted', 'pending_qc', 'completed') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert status enum
        \Illuminate\Support\Facades\DB::table('pr_design_grafis_works')->where('status', 'pending_qc')->update(['status' => 'submitted']);
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE pr_design_grafis_works MODIFY COLUMN status ENUM('pending', 'in_progress', 'submitted', 'completed') NOT NULL DEFAULT 'pending'");
    }
};
