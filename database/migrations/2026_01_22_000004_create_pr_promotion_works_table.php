<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Promotion Works table untuk Program Regular
     * Menangani BTS video, talent photos, dan sharing konten
     */
    public function up(): void
    {
        Schema::create('pr_promotion_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pr_episode_id')->constrained('pr_episodes')->onDelete('cascade');

            // Work Type
            $table->enum('work_type', [
                'bts_video',           // BTS video dan foto talent
                'bts_photo',           // BTS foto
                'highlight_ig',        // Highlight Instagram (share)
                'highlight_facebook',  // Highlight Facebook (share)
                'highlight_tv',        // Highlight TV
                'story_ig',            // Story Instagram
                'reels_facebook',      // Reels Facebook
                'tiktok',              // TikTok
                'website_content',     // Website content
                'share_facebook',      // Share link ke Facebook
                'share_wa_group'       // Share link ke WA Group
            ])->default('bts_video');

            // Shooting Schedule (dari Creative)
            $table->date('shooting_date')->nullable();
            $table->time('shooting_time')->nullable();
            $table->json('location_data')->nullable();
            $table->text('shooting_notes')->nullable();

            // Uploaded Files
            $table->json('file_paths')->nullable(); // Array of uploaded files

            // Sharing Proof (untuk share_facebook, share_wa_group)
            $table->json('sharing_proof')->nullable(); // Screenshots, links, timestamps

            // Status
            $table->enum('status', [
                'planning',        // Baru dibuat, planning
                'shooting',        // Sedang syuting/create content
                'editing',         // Sedang edit (jika perlu)
                'sharing',         // Sedang share konten
                'completed'        // Selesai
            ])->default('planning');

            // Metadata
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('completion_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['pr_episode_id', 'work_type']);
            $table->index(['pr_episode_id', 'status']);
            $table->index('created_by');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_promotion_works');
    }
};
