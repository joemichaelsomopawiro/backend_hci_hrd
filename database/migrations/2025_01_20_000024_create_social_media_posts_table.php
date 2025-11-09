<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Social Media Posts - Untuk tracking posting ke social media
     */
    public function up(): void
    {
        Schema::create('social_media_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
            $table->foreignId('promotion_work_id')->nullable()->constrained('promotion_works')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // Promosi
            $table->enum('platform', [
                'youtube',
                'instagram',
                'facebook', 
                'tiktok',
                'twitter',
                'website'
            ]);
            $table->string('post_id')->nullable(); // ID post di platform
            $table->string('title'); // Judul post
            $table->text('description')->nullable(); // Deskripsi
            $table->text('content')->nullable(); // Konten
            $table->json('hashtags')->nullable(); // Hashtags
            $table->json('tags')->nullable(); // Tags
            $table->json('mentions')->nullable(); // Mentions
            $table->json('media_files')->nullable(); // File media
            $table->string('thumbnail_url')->nullable(); // URL thumbnail
            $table->string('post_url')->nullable(); // URL post
            $table->enum('status', [
                'draft',           // Draft
                'scheduled',       // Terjadwal
                'published',       // Sudah dipublish
                'failed',          // Gagal publish
                'deleted'          // Dihapus
            ])->default('draft');
            $table->timestamp('scheduled_at')->nullable(); // Jadwal publish
            $table->timestamp('published_at')->nullable(); // Waktu publish
            $table->json('engagement_metrics')->nullable(); // Metrics engagement
            $table->text('notes')->nullable(); // Catatan
            $table->timestamps();
            
            // Indexes
            $table->index('episode_id');
            $table->index('platform');
            $table->index('status');
            $table->index('scheduled_at');
            $table->index('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_media_posts');
    }
};













