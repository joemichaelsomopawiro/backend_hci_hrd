<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add episode_id to production_teams_assignment to support episode-based workflow
     */
    public function up(): void
    {
        Schema::table('production_teams_assignment', function (Blueprint $table) {
            // Make music_submission_id nullable
            $table->foreignId('music_submission_id')->nullable()->change();
            
            // Add episode_id for episode-based workflow
            $table->foreignId('episode_id')->nullable()->after('music_submission_id')
                ->constrained('episodes')->onDelete('cascade');
            
            // Update index
            $table->index(['episode_id', 'team_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_teams_assignment', function (Blueprint $table) {
            $table->dropForeign(['episode_id']);
            $table->dropColumn('episode_id');
            $table->dropIndex(['episode_id', 'team_type', 'status']);
            
            // Make music_submission_id not nullable again
            $table->foreignId('music_submission_id')->nullable(false)->change();
        });
    }
};








