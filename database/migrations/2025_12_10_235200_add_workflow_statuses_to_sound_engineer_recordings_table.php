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
        // Update enum status to include new workflow statuses
        DB::statement("ALTER TABLE sound_engineer_recordings MODIFY COLUMN status ENUM('draft', 'pending', 'in_progress', 'scheduled', 'ready', 'recording', 'completed', 'reviewed') DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE sound_engineer_recordings MODIFY COLUMN status ENUM('draft', 'recording', 'completed', 'reviewed') DEFAULT 'draft'");
    }
};
