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
        $tables = [
            'editor_works',
            'creative_works',
            'promotion_works',
            'quality_control_works',
            'produksi_works',
            'broadcasting_works',
            'design_grafis_works',
            'pr_editor_works',
            'pr_creative_works',
            'pr_promotion_works',
            'pr_quality_control_works',
            'pr_produksi_works',
            'pr_broadcasting_works'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->unsignedBigInteger('originally_assigned_to')->nullable()->after('id');
                    $table->boolean('was_reassigned')->default(false)->after('originally_assigned_to');
                    
                    $table->index('originally_assigned_to', 'idx_original_assigned');
                    $table->index('was_reassigned', 'idx_was_reassigned');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'editor_works',
            'creative_works',
            'promotion_works',
            'quality_control_works',
            'produksi_works',
            'broadcasting_works',
            'design_grafis_works',
            'pr_editor_works',
            'pr_creative_works',
            'pr_promotion_works',
            'pr_quality_control_works',
            'pr_produksi_works',
            'pr_broadcasting_works'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropIndex('idx_original_assigned');
                    $table->dropIndex('idx_was_reassigned');
                    $table->dropColumn(['originally_assigned_to', 'was_reassigned']);
                });
            }
        }
    }
};
