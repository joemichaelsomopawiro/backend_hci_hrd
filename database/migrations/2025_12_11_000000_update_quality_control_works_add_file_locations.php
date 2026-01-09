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
        Schema::table('quality_control_works', function (Blueprint $table) {
            // File locations dari Editor Promosi dan Design Grafis
            $table->json('editor_promosi_file_locations')->nullable()->after('files_to_check');
            $table->json('design_grafis_file_locations')->nullable()->after('editor_promosi_file_locations');
            
            // QC results untuk berbagai jenis konten
            $table->json('qc_results')->nullable()->after('screenshots');
            // Structure: {
            //   "bts_video": {"status": "approved/rejected", "notes": "...", "score": 85},
            //   "iklan_episode_tv": {...},
            //   "iklan_highlight_episode_ig": {...},
            //   "highlight_episode_tv": {...},
            //   "highlight_episode_face": {...},
            //   "thumbnail_yt": {...},
            //   "thumbnail_bts": {...}
            // }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quality_control_works', function (Blueprint $table) {
            $table->dropColumn([
                'editor_promosi_file_locations',
                'design_grafis_file_locations',
                'qc_results'
            ]);
        });
    }
};

