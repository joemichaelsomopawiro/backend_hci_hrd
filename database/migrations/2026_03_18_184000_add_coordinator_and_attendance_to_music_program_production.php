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
        Schema::table('production_team_members', function (Blueprint $table) {
            if (!Schema::hasColumn('production_team_members', 'is_coordinator')) {
                $table->boolean('is_coordinator')->default(false)->after('role');
            }
        });

        Schema::table('produksi_works', function (Blueprint $table) {
            if (!Schema::hasColumn('produksi_works', 'crew_attendances')) {
                $table->json('crew_attendances')->nullable()->after('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_team_members', function (Blueprint $table) {
            if (Schema::hasColumn('production_team_members', 'is_coordinator')) {
                $table->dropColumn('is_coordinator');
            }
        });

        Schema::table('produksi_works', function (Blueprint $table) {
            if (Schema::hasColumn('produksi_works', 'crew_attendances')) {
                $table->dropColumn('crew_attendances');
            }
        });
    }
};
