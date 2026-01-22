<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Broadcasting Works table untuk Program Regular
     * Menangani upload ke YouTube, website, dan scheduling
     */
    public function up(): void
    {
        Schema::create('pr_broadcasting_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pr_episode_id')->constrained('pr_episodes')->onDelete('cascade');

            // Work Type
            $table->enum('work_type', [
                'youtube_upload',      // Upload ke YouTube
                'website_upload',      // Upload ke website
                'playlist_update',     // Update playlist
                'schedule_update',     // Update jadwal tayang
                'metadata_update',     // Update metadata/SEO
                'thumbnail_upload',    // Upload thumbnail
                'description_update',  // Update deskripsi
                'main_episode'         // Upload episode utama
            ])->default('main_episode');

            // YouTube Data
            $table->string('youtube_url')->nullable();
            $table->string('youtube_video_id')->nullable();
            $table->string('title')->nullable(); // SEO optimized title
            $table->text('description')->nullable(); // SEO optimized description
            $table->json('metadata')->nullable(); // Tags, category, privacy status, dll

            // Website Data
            $table->string('website_url')->nullable();

            // Thumbnail
            $table->string('thumbnail_path')->nullable();

            // Video File
            $table->string('video_file_path')->nullable();

            // Playlist & Scheduling
            $table->json('playlist_data')->nullable(); // Playlist info
            $table->timestamp('scheduled_time')->nullable();

            // Status
            $table->enum('status', [
                'preparing',       // Sedang prepare
                'pending',         // Menunggu upload
                'uploading',       // Sedang upload
                'processing',      // YouTube processing
                'published',       // Sudah publish
                'scheduled',       // Sudah schedule
                'failed',          // Upload gagal
                'cancelled'        // Dibatalkan
            ])->default('preparing');

            // Metadata
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('published_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['pr_episode_id', 'work_type']);
            $table->index(['pr_episode_id', 'status']);
            $table->index('created_by');
            $table->index('status');
            $table->index('scheduled_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_broadcasting_works');
    }
};
