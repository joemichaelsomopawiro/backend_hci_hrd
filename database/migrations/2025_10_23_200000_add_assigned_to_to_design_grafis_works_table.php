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
        if (Schema::hasTable('design_grafis_works')) {
            Schema::table('design_grafis_works', function (Blueprint $table) {
                if (!Schema::hasColumn('design_grafis_works', 'assigned_to')) {
                    $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null')->after('episode_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('design_grafis_works')) {
            Schema::table('design_grafis_works', function (Blueprint $table) {
                if (Schema::hasColumn('design_grafis_works', 'assigned_to')) {
                    $table->dropForeign(['assigned_to']);
                    $table->dropColumn('assigned_to');
                }
            });
        }
    }
};
