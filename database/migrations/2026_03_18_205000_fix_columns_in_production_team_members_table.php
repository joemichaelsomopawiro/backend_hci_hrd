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
        Schema::table('production_team_members', function (Blueprint $table) {
            if (!Schema::hasColumn('production_team_members', 'is_coordinator')) {
                $table->boolean('is_coordinator')->default(false)->after('role');
            }
            
            // In case production_team_id is missing or needs to be nullable
            if (!Schema::hasColumn('production_team_members', 'production_team_id')) {
                $table->foreignId('production_team_id')->nullable()->after('id')
                    ->constrained('production_teams')->onDelete('cascade');
            } else {
                // If it exists, make sure it is nullable to support assignment-only rows
                $table->unsignedBigInteger('production_team_id')->nullable()->change();
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
            // We usually don't drop structural foreign keys in down() if they might contain data
        });
    }
};
