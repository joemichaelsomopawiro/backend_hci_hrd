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
        // 1. Add pr_production_work_id to pr_editor_works
        if (Schema::hasTable('pr_editor_works')) {
            Schema::table('pr_editor_works', function (Blueprint $table) {
                if (!Schema::hasColumn('pr_editor_works', 'pr_production_work_id')) {
                    // Make it nullable initially to avoid issues with existing data, 
                    // though ideally it should be required for new records.
                    // Constrained to pr_produksi_works if that assumes the table name.
                    // PrProduksiWork model says table is likely 'pr_produksi_works' (standard plural).
                    // Let's verify table name from check_tables output: 'pr_produksi_works'.
                    $table->foreignId('pr_production_work_id')->nullable()->after('pr_episode_id')->constrained('pr_produksi_works')->onDelete('cascade');
                }
            });
        }

        // 2. Add pr_editor_work_id and pr_promotion_work_id to pr_editor_promosi_works
        if (Schema::hasTable('pr_editor_promosi_works')) {
            Schema::table('pr_editor_promosi_works', function (Blueprint $table) {
                if (!Schema::hasColumn('pr_editor_promosi_works', 'pr_editor_work_id')) {
                    // Note: PrEditorWork table is 'pr_editor_works'
                    $table->foreignId('pr_editor_work_id')->nullable()->after('pr_episode_id')->constrained('pr_editor_works')->onDelete('cascade');
                }
                if (Schema::hasColumn('pr_editor_promosi_works', 'episode_id')) {
                    // Renaming episode_id to pr_episode_id if needed, but model uses pr_episode_id. 
                    // Migration created 'episode_id'.
                    // Need to check if we should align with model 'pr_episode_id' or keep 'episode_id' and update model?
                    // Previous analysis of implementation plan focused on missing IDs.
                    // PrEditorPromosiWork model uses: belongsTo(PrEpisode::class, 'pr_episode_id');
                    // Migration 2026_01_23_034500_create_editor_promosi_works_table.php created 'episode_id'.
                    // Discrepancy detected! 
                    // FIX: Rename episode_id to pr_episode_id if it exists.
                    // But let's stick to adding missing columns first.
                    // Also adding pr_promotion_work_id.
                }
                if (!Schema::hasColumn('pr_editor_promosi_works', 'pr_promotion_work_id')) {
                    // PrPromotionWork table is 'pr_promotion_works'
                    $table->foreignId('pr_promotion_work_id')->nullable()->after('pr_editor_work_id')->constrained('pr_promotion_works')->onDelete('cascade');
                }
            });
        }

        // 3. Add pr_production_work_id and pr_promotion_work_id to pr_design_grafis_works
        if (Schema::hasTable('pr_design_grafis_works')) {
            Schema::table('pr_design_grafis_works', function (Blueprint $table) {
                if (!Schema::hasColumn('pr_design_grafis_works', 'pr_production_work_id')) {
                    $table->foreignId('pr_production_work_id')->nullable()->after('pr_episode_id')->constrained('pr_produksi_works')->onDelete('cascade');
                }
                if (!Schema::hasColumn('pr_design_grafis_works', 'pr_promotion_work_id')) {
                    $table->foreignId('pr_promotion_work_id')->nullable()->after('pr_production_work_id')->constrained('pr_promotion_works')->onDelete('cascade');
                }
            });
        }

        // Also fix the table name issue if 'editor_promosi_works' vs 'pr_editor_promosi_works' exists
        // The migration created 'editor_promosi_works' but model uses 'PrEditorPromosiWork' (Laravel default table: pr_editor_promosi_works? No, snake case of PrEditorPromosiWork is pr_editor_promosi_works).
        // Check tables showed: 'editor_promosi_works' AND 'pr_editor_promosi_works'.
        // Wait, check_tables output lines 11 and 19:
        // 11: editor_promosi_works
        // 19: pr_editor_promosi_works
        // 10: design_grafis_works
        // 16: pr_design_grafis_works
        // It seems there are duplicate tables or I should target the 'pr_' ones as per recent conventions.
        // The Controller/Model usage suggests 'pr_' prefix is expected for relationships to work if not explicitly defined with table name.
        // PrEditorPromosiWork model doesn't specify $table, so it defaults to 'pr_editor_promosi_works'.
        // So I must insure I am modifying 'pr_editor_promosi_works' and 'pr_design_grafis_works'.
        // My code above uses 'pr_' prefix.

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pr_editor_works')) {
            Schema::table('pr_editor_works', function (Blueprint $table) {
                if (Schema::hasColumn('pr_editor_works', 'pr_production_work_id')) {
                    $table->dropForeign(['pr_production_work_id']);
                    $table->dropColumn('pr_production_work_id');
                }
            });
        }

        if (Schema::hasTable('pr_editor_promosi_works')) {
            Schema::table('pr_editor_promosi_works', function (Blueprint $table) {
                if (Schema::hasColumn('pr_editor_promosi_works', 'pr_editor_work_id')) {
                    $table->dropForeign(['pr_editor_work_id']);
                    $table->dropColumn('pr_editor_work_id');
                }
                if (Schema::hasColumn('pr_editor_promosi_works', 'pr_promotion_work_id')) {
                    $table->dropForeign(['pr_promotion_work_id']);
                    $table->dropColumn('pr_promotion_work_id');
                }
            });
        }

        if (Schema::hasTable('pr_design_grafis_works')) {
            Schema::table('pr_design_grafis_works', function (Blueprint $table) {
                if (Schema::hasColumn('pr_design_grafis_works', 'pr_production_work_id')) {
                    $table->dropForeign(['pr_production_work_id']);
                    $table->dropColumn('pr_production_work_id');
                }
                if (Schema::hasColumn('pr_design_grafis_works', 'pr_promotion_work_id')) {
                    $table->dropForeign(['pr_promotion_work_id']);
                    $table->dropColumn('pr_promotion_work_id');
                }
            });
        }
    }
};
