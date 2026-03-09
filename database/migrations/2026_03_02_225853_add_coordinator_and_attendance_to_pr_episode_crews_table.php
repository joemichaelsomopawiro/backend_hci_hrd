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
        // Add is_coordinator to pr_episode_crews if not exists
        if (Schema::hasTable('pr_episode_crews') && !Schema::hasColumn('pr_episode_crews', 'is_coordinator')) {
            Schema::table('pr_episode_crews', function (Blueprint $table) {
                $table->boolean('is_coordinator')->default(false)->after('role');
            });
        }

        // Add crew_attendances JSON to pr_produksi_works if not exists
        if (Schema::hasTable('pr_produksi_works') && !Schema::hasColumn('pr_produksi_works', 'crew_attendances')) {
            Schema::table('pr_produksi_works', function (Blueprint $table) {
                $table->json('crew_attendances')->nullable()->after('shooting_notes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pr_episode_crews') && Schema::hasColumn('pr_episode_crews', 'is_coordinator')) {
            Schema::table('pr_episode_crews', function (Blueprint $table) {
                $table->dropColumn('is_coordinator');
            });
        }

        if (Schema::hasTable('pr_produksi_works') && Schema::hasColumn('pr_produksi_works', 'crew_attendances')) {
            Schema::table('pr_produksi_works', function (Blueprint $table) {
                $table->dropColumn('crew_attendances');
            });
        }
    }
};
