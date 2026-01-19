<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL doesn't support ALTER ENUM directly, so we need to use raw SQL
        DB::statement("ALTER TABLE promotion_works MODIFY COLUMN work_type ENUM(
            'bts_video',
            'bts_photo',
            'highlight_ig',
            'highlight_facebook',
            'highlight_tv',
            'iklan_episode_tv',
            'story_ig',
            'reels_facebook',
            'tiktok',
            'website_content',
            'share_facebook',
            'share_wa_group'
        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE promotion_works MODIFY COLUMN work_type ENUM(
            'bts_video',
            'bts_photo',
            'highlight_ig',
            'highlight_facebook',
            'highlight_tv',
            'iklan_episode_tv',
            'story_ig',
            'reels_facebook',
            'tiktok',
            'website_content'
        )");
    }
};
