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
        // Step 1: Relax column to VARCHAR to avoid enum truncation warnings on existing data
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE design_grafis_works MODIFY COLUMN status VARCHAR(50) NOT NULL");

        // Step 2: Normalise any unexpected statuses into a safe default
        \Illuminate\Support\Facades\DB::table('design_grafis_works')
            ->whereNotIn('status', ['pending', 'in_progress', 'submitted', 'pending_qc', 'completed'])
            ->update(['status' => 'pending']);

        // Step 3: Re-apply strict ENUM with the new pending_qc value
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE design_grafis_works MODIFY COLUMN status ENUM('pending', 'in_progress', 'submitted', 'pending_qc', 'completed') NOT NULL DEFAULT 'pending'");

        // Update submitted works to pending_qc (optional, depending on requirement)
        // \Illuminate\Support\Facades\DB::table('design_grafis_works')->where('status', 'submitted')->update(['status' => 'pending_qc']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert status enum (be careful if there are pending_qc records)
        \Illuminate\Support\Facades\DB::table('design_grafis_works')->where('status', 'pending_qc')->update(['status' => 'submitted']);
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE design_grafis_works MODIFY COLUMN status ENUM('pending', 'in_progress', 'submitted', 'completed') NOT NULL DEFAULT 'pending'");
    }
};
