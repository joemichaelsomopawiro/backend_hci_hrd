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
        // Check if table already exists
        if (!Schema::hasTable('social_media_posts')) {
            Schema::create('social_media_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
            $table->enum('platform', ['facebook', 'instagram', 'tiktok', 'website']);
            $table->enum('content_type', ['story', 'reels', 'post', 'highlight']);
            $table->string('title');
            $table->text('content');
            $table->json('hashtags')->nullable();
            $table->json('file_paths')->nullable();
            $table->timestamp('scheduled_time')->nullable();
            $table->timestamp('published_time')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'published', 'failed'])->default('draft');
            $table->json('engagement_data')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_media_posts');
    }
};
