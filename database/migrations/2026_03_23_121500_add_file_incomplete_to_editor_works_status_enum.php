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
        // Add 'file_incomplete' to the status enum
        DB::statement("ALTER TABLE editor_works MODIFY COLUMN status ENUM('draft', 'editing', 'completed', 'reviewed', 'approved', 'submitted', 'rejected', 'file_incomplete') DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum (removing 'file_incomplete')
        // Note: Any records with 'file_incomplete' status will be in an invalid state after this rollback
        DB::statement("ALTER TABLE editor_works MODIFY COLUMN status ENUM('draft', 'editing', 'completed', 'reviewed', 'approved', 'submitted', 'rejected') DEFAULT 'draft'");
    }
};
