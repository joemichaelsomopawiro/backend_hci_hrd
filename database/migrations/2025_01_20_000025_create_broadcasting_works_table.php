<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Broadcasting Works - Untuk tim Broadcasting
     */
    public function up(): void
    {
        Schema::create('broadcasting_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // Broadcasting
            $table->enum('work_type', [
                'youtube_upload',      // Upload ke YouTube
                'website_upload',      // Upload ke website
                'playlist_update',     // Update playlist
                'schedule_update',     // Update jadwal tayang
                'metadata_update',     // Update metadata
                'thumbnail_upload',    // Upload thumbnail
                'description_update'   // Update deskripsi
            ]);
            $table->string('title'); // Judul
            $table->text('description')->nullable(); // Deskripsi
            $table->json('metadata')->nullable(); // Metadata (tags, category, etc.)
            $table->string('video_file_path')->nullable(); // Path file video
            $table->string('thumbnail_path')->nullable(); // Path thumbnail
            $table->string('youtube_video_id')->nullable(); // ID video YouTube
            $table->string('youtube_url')->nullable(); // URL YouTube
            $table->string('website_url')->nullable(); // URL website
            $table->json('playlist_data')->nullable(); // Data playlist
            $table->timestamp('scheduled_time')->nullable(); // Jadwal tayang
            $table->timestamp('published_time')->nullable(); // Waktu publish
            $table->enum('status', [
                'preparing',        // Persiapan
                'uploading',        // Sedang upload
                'processing',       // Sedang proses
                'published',        // Sudah dipublish
                'scheduled',        // Terjadwal
                'failed',           // Gagal
                'cancelled'         // Dibatalkan
            ])->default('preparing');
            $table->json('upload_progress')->nullable(); // Progress upload
            $table->json('platform_responses')->nullable(); // Response dari platform
            $table->text('error_message')->nullable(); // Pesan error
            $table->timestamps();
            
            // Indexes
            $table->index('episode_id');
            $table->index('work_type');
            $table->index('status');
            $table->index('scheduled_time');
            $table->index('youtube_video_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcasting_works');
    }
};













