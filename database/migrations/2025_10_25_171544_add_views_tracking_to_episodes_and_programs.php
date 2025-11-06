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
        // Add views tracking to episodes
        Schema::table('episodes', function (Blueprint $table) {
            if (!Schema::hasColumn('episodes', 'actual_views')) {
                $table->bigInteger('actual_views')->default(0)->after('status');
            }
            if (!Schema::hasColumn('episodes', 'views_last_updated')) {
                $table->timestamp('views_last_updated')->nullable()->after('actual_views');
            }
            if (!Schema::hasColumn('episodes', 'views_growth_rate')) {
                $table->decimal('views_growth_rate', 5, 2)->nullable()->after('views_last_updated')->comment('Persentase pertumbuhan views per minggu');
            }
        });
        
        // Add performance tracking to programs
        Schema::table('programs', function (Blueprint $table) {
            if (!Schema::hasColumn('programs', 'total_actual_views')) {
                $table->bigInteger('total_actual_views')->default(0)->after('target_views_per_episode');
            }
            if (!Schema::hasColumn('programs', 'average_views_per_episode')) {
                $table->decimal('average_views_per_episode', 10, 2)->default(0)->after('total_actual_views');
            }
            if (!Schema::hasColumn('programs', 'performance_status')) {
                $table->enum('performance_status', ['good', 'warning', 'poor', 'pending'])->default('pending')->after('average_views_per_episode');
            }
            if (!Schema::hasColumn('programs', 'last_performance_check')) {
                $table->timestamp('last_performance_check')->nullable()->after('performance_status');
            }
            if (!Schema::hasColumn('programs', 'auto_close_enabled')) {
                $table->boolean('auto_close_enabled')->default(true)->after('last_performance_check')->comment('Auto-close jika performa buruk');
            }
            if (!Schema::hasColumn('programs', 'min_episodes_before_evaluation')) {
                $table->integer('min_episodes_before_evaluation')->default(4)->after('auto_close_enabled')->comment('Min episode sebelum evaluasi performa');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropColumn(['actual_views', 'views_last_updated', 'views_growth_rate']);
        });
        
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn([
                'total_actual_views',
                'average_views_per_episode',
                'performance_status',
                'last_performance_check',
                'auto_close_enabled',
                'min_episodes_before_evaluation'
            ]);
        });
    }
};
