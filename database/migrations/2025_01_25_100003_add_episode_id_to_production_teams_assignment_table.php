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
        if (Schema::hasTable('production_teams_assignment')) {
            Schema::table('production_teams_assignment', function (Blueprint $table) {
                // Make music_submission_id nullable if column exists
                if (Schema::hasColumn('production_teams_assignment', 'music_submission_id')) {
                    $table->foreignId('music_submission_id')->nullable()->change();
                }
                
                // Add episode_id for episode-based workflow if not exists
                if (!Schema::hasColumn('production_teams_assignment', 'episode_id')) {
                    $table->foreignId('episode_id')->nullable()->after('music_submission_id')
                        ->constrained('episodes')->onDelete('cascade');
                }
                
                // Update index if columns exist
                if (Schema::hasColumn('production_teams_assignment', 'episode_id') && 
                    Schema::hasColumn('production_teams_assignment', 'team_type') &&
                    Schema::hasColumn('production_teams_assignment', 'status')) {
                    $table->index(['episode_id', 'team_type', 'status']);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('production_teams_assignment')) {
            Schema::table('production_teams_assignment', function (Blueprint $table) {
                if (Schema::hasColumn('production_teams_assignment', 'episode_id')) {
                    $table->dropForeign(['episode_id']);
                    $table->dropColumn('episode_id');
                }
                
                if (Schema::hasColumn('production_teams_assignment', 'episode_id') && 
                    Schema::hasColumn('production_teams_assignment', 'team_type') &&
                    Schema::hasColumn('production_teams_assignment', 'status')) {
                    $table->dropIndex(['episode_id', 'team_type', 'status']);
                }
                
                // Make music_submission_id not nullable again if column exists
                if (Schema::hasColumn('production_teams_assignment', 'music_submission_id')) {
                    $table->foreignId('music_submission_id')->nullable(false)->change();
                }
            });
        }
    }
};








