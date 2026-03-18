<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Program Musik: genre (target kemana) + batas budget oleh Program Manager.
     */
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            if (!Schema::hasColumn('programs', 'genre')) {
                $table->string('genre', 100)->nullable()->after('category');
            }
            if (!Schema::hasColumn('programs', 'target_audience')) {
                $table->string('target_audience', 255)->nullable()->after('genre');
            }
            if (!Schema::hasColumn('programs', 'max_budget_per_episode')) {
                $table->decimal('max_budget_per_episode', 14, 2)->nullable()->after('budget_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            if (Schema::hasColumn('programs', 'genre')) {
                $table->dropColumn('genre');
            }
            if (Schema::hasColumn('programs', 'target_audience')) {
                $table->dropColumn('target_audience');
            }
            if (Schema::hasColumn('programs', 'max_budget_per_episode')) {
                $table->dropColumn('max_budget_per_episode');
            }
        });
    }
};
