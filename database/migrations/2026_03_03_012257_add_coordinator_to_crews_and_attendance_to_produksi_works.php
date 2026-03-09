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
        Schema::table('pr_program_crews', function (Blueprint $table) {
            if (!Schema::hasColumn('pr_program_crews', 'is_coordinator')) {
                $table->boolean('is_coordinator')->default(false)->after('role');
            }
        });

        Schema::table('pr_episode_crews', function (Blueprint $table) {
            if (!Schema::hasColumn('pr_episode_crews', 'is_coordinator')) {
                $table->boolean('is_coordinator')->default(false)->after('role');
            }
        });

        Schema::table('pr_produksi_works', function (Blueprint $table) {
            if (!Schema::hasColumn('pr_produksi_works', 'crew_attendances')) {
                $table->json('crew_attendances')->nullable()->after('shooting_notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pr_program_crews', function (Blueprint $table) {
            if (Schema::hasColumn('pr_program_crews', 'is_coordinator')) {
                $table->dropColumn('is_coordinator');
            }
        });

        Schema::table('pr_episode_crews', function (Blueprint $table) {
            if (Schema::hasColumn('pr_episode_crews', 'is_coordinator')) {
                $table->dropColumn('is_coordinator');
            }
        });

        Schema::table('pr_produksi_works', function (Blueprint $table) {
            if (Schema::hasColumn('pr_produksi_works', 'crew_attendances')) {
                $table->dropColumn('crew_attendances');
            }
        });
    }
};
