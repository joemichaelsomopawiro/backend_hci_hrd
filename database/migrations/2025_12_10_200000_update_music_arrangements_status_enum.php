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
        // Update enum status untuk support workflow baru
        // Workflow: song_proposal -> song_approved/song_rejected -> arrangement_in_progress -> arrangement_submitted -> arrangement_approved
        
        DB::statement("ALTER TABLE music_arrangements MODIFY COLUMN status ENUM(
            'draft',
            'song_proposal',
            'song_approved',
            'song_rejected',
            'arrangement_in_progress',
            'submitted',
            'arrangement_submitted',
            'approved',
            'arrangement_approved',
            'rejected',
            'arrangement_rejected',
            'revised'
        ) DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE music_arrangements MODIFY COLUMN status ENUM(
            'draft',
            'submitted',
            'approved',
            'rejected',
            'revised'
        ) DEFAULT 'draft'");
    }
};

