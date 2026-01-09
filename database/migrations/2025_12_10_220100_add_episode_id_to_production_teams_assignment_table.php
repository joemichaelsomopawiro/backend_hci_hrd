<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('production_teams_assignment', function (Blueprint $table) {
            // Add episode_id untuk link ke episode (bukan hanya music_submission_id)
            if (!Schema::hasColumn('production_teams_assignment', 'episode_id')) {
                $table->foreignId('episode_id')->nullable()->after('music_submission_id')->constrained('episodes')->onDelete('cascade');
                $table->index(['episode_id', 'team_type', 'status'], 'prod_teams_episode_type_status_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_teams_assignment', function (Blueprint $table) {
            if (Schema::hasColumn('production_teams_assignment', 'episode_id')) {
                $table->dropForeign(['episode_id']);
                $table->dropIndex('prod_teams_episode_type_status_idx');
                $table->dropColumn('episode_id');
            }
        });
    }
};

