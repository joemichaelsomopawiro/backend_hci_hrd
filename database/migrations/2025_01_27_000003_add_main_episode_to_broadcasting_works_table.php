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
        DB::statement("ALTER TABLE broadcasting_works MODIFY COLUMN work_type ENUM(
            'youtube_upload',
            'website_upload',
            'playlist_update',
            'schedule_update',
            'metadata_update',
            'thumbnail_upload',
            'description_update',
            'main_episode'
        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE broadcasting_works MODIFY COLUMN work_type ENUM(
            'youtube_upload',
            'website_upload',
            'playlist_update',
            'schedule_update',
            'metadata_update',
            'thumbnail_upload',
            'description_update'
        )");
    }
};
