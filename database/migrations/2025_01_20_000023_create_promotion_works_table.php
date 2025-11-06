<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Promotion Works - Untuk tim Promosi
     */
    public function up(): void
    {
        Schema::create('promotion_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // Promosi
            $table->enum('work_type', [
                'bts_video',        // Video BTS
                'bts_photo',        // Foto BTS
                'highlight_ig',     // Highlight Instagram
                'highlight_facebook', // Highlight Facebook
                'highlight_tv',    // Highlight TV
                'story_ig',         // Story Instagram
                'reels_facebook',   // Reels Facebook
                'tiktok',           // TikTok content
                'website_content'   // Konten website
            ]);
            $table->string('title'); // Judul konten
            $table->text('description')->nullable(); // Deskripsi
            $table->text('content_plan')->nullable(); // Rencana konten
            $table->json('talent_data')->nullable(); // Data talent yang terlibat
            $table->json('location_data')->nullable(); // Data lokasi
            $table->json('equipment_needed')->nullable(); // Alat yang dibutuhkan
            $table->date('shooting_date')->nullable(); // Tanggal shooting
            $table->time('shooting_time')->nullable(); // Waktu shooting
            $table->text('shooting_notes')->nullable(); // Catatan shooting
            $table->json('file_paths')->nullable(); // Path file hasil
            $table->json('social_media_links')->nullable(); // Link social media
            $table->enum('status', [
                'planning',         // Perencanaan
                'shooting',         // Sedang shooting
                'editing',          // Sedang edit
                'review',           // Review
                'approved',         // Disetujui
                'published',        // Sudah dipublish
                'rejected'          // Ditolak
            ])->default('planning');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('episode_id');
            $table->index('work_type');
            $table->index('status');
            $table->index('shooting_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_works');
    }
};













