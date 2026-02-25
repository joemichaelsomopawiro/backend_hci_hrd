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
        // For MySQL, we need to modify the ENUM directly
        DB::statement("ALTER TABLE editor_works MODIFY COLUMN status ENUM('draft', 'editing', 'completed', 'reviewed', 'approved', 'submitted', 'rejected') DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE editor_works MODIFY COLUMN status ENUM('draft', 'editing', 'completed', 'reviewed', 'approved') DEFAULT 'draft'");
    }
};
