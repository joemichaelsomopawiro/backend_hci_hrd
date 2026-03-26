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
        Schema::table('pr_programs', function (Blueprint $table) {
            $table->decimal('max_budget_per_episode', 15, 2)->nullable()->after('read_at');
            $table->integer('target_views')->nullable()->after('max_budget_per_episode');
            $table->integer('target_likes')->nullable()->after('target_views');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pr_programs', function (Blueprint $table) {
            $table->dropColumn(['max_budget_per_episode', 'target_views', 'target_likes']);
        });
    }
};
