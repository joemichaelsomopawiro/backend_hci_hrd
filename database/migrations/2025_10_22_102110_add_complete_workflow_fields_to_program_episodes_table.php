<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Menambahkan semua fields yang dibutuhkan untuk complete workflow:
     * Creative → Producer → Produksi → Editor → QC → Broadcasting → Promosi → Design Grafis
     */
    public function up(): void
    {
        // Check if table exists first
        if (!Schema::hasTable('program_episodes')) {
            // Table belum ada, skip migration ini
            // Run migration 2025_10_09_000004_create_program_episodes_table.php terlebih dahulu
            return;
        }

        Schema::table('program_episodes', function (Blueprint $table) {
            // ========================================
            // CREATIVE FIELDS
            // ========================================
            $table->timestamp('script_submitted_at')->nullable()->after('notes');
            $table->foreignId('script_submitted_by')->nullable()->after('script_submitted_at')->constrained('users')->onDelete('set null');
            
            // ========================================
            // PRODUCER REVIEW FIELDS
            // ========================================
            $table->timestamp('rundown_approved_at')->nullable();
            $table->foreignId('rundown_approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('rundown_rejected_at')->nullable();
            $table->foreignId('rundown_rejected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('rundown_rejection_notes')->nullable();
            $table->json('rundown_revision_points')->nullable();
            $table->text('producer_notes')->nullable();
            
            // ========================================
            // PRODUKSI FIELDS
            // ========================================
            $table->json('raw_file_urls')->nullable();
            $table->text('shooting_notes')->nullable();
            $table->date('actual_shooting_date')->nullable();
            $table->timestamp('shooting_completed_at')->nullable();
            $table->foreignId('shooting_completed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('budget_talent', 15, 2)->nullable();
            
            // ========================================
            // EDITOR FIELDS
            // ========================================
            $table->enum('editing_status', ['pending', 'in_progress', 'draft', 'completed', 'revision'])->nullable();
            $table->timestamp('editing_started_at')->nullable();
            $table->foreignId('editing_started_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('editing_notes')->nullable();
            $table->json('editing_drafts')->nullable();
            $table->string('final_file_url')->nullable();
            $table->text('editing_completion_notes')->nullable();
            $table->integer('edited_duration_minutes')->nullable();
            $table->decimal('final_file_size_mb', 10, 2)->nullable();
            $table->timestamp('editing_completed_at')->nullable();
            $table->foreignId('editing_completed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->json('editing_revisions')->nullable();
            $table->timestamp('revision_acknowledged_at')->nullable();
            $table->foreignId('revision_acknowledged_by')->nullable()->constrained('users')->onDelete('set null');
            
            // ========================================
            // QC FIELDS
            // ========================================
            $table->timestamp('qc_approved_at')->nullable();
            $table->foreignId('qc_approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('qc_revision_requested_at')->nullable();
            $table->foreignId('qc_revision_requested_by')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('qc_revision_count')->default(0);
            
            // ========================================
            // BROADCASTING FIELDS
            // ========================================
            // Metadata SEO
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->json('seo_tags')->nullable();
            $table->string('youtube_category')->nullable();
            $table->enum('youtube_privacy', ['public', 'unlisted', 'private'])->default('public');
            $table->timestamp('metadata_updated_at')->nullable();
            $table->foreignId('metadata_updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            // YouTube upload
            $table->string('youtube_url')->nullable();
            $table->string('youtube_video_id', 50)->nullable();
            $table->enum('youtube_upload_status', ['pending', 'uploading', 'completed', 'failed'])->nullable();
            $table->timestamp('youtube_upload_started_at')->nullable();
            $table->timestamp('youtube_uploaded_at')->nullable();
            $table->foreignId('youtube_upload_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Website publish
            $table->string('website_url')->nullable();
            $table->timestamp('website_published_at')->nullable();
            $table->foreignId('website_published_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Broadcasting completion
            $table->text('broadcast_notes')->nullable();
            $table->timestamp('actual_air_date')->nullable();
            $table->timestamp('broadcast_completed_at')->nullable();
            $table->foreignId('broadcast_completed_by')->nullable()->constrained('users')->onDelete('set null');
            
            // ========================================
            // DESIGN GRAFIS FIELDS
            // ========================================
            $table->string('thumbnail_youtube')->nullable();
            $table->string('thumbnail_bts')->nullable();
            $table->json('design_assets_talent_photos')->nullable();
            $table->json('design_assets_bts_photos')->nullable();
            $table->json('design_assets_production_files')->nullable();
            $table->timestamp('design_assets_received_at')->nullable();
            $table->foreignId('design_assets_received_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('design_assets_notes')->nullable();
            $table->timestamp('thumbnail_youtube_uploaded_at')->nullable();
            $table->foreignId('thumbnail_youtube_uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('thumbnail_youtube_notes')->nullable();
            $table->timestamp('thumbnail_bts_uploaded_at')->nullable();
            $table->foreignId('thumbnail_bts_uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('thumbnail_bts_notes')->nullable();
            $table->timestamp('design_completed_at')->nullable();
            $table->foreignId('design_completed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('design_completion_notes')->nullable();
            
            // ========================================
            // PROMOSI FIELDS
            // ========================================
            // BTS Content (Tahap 1)
            $table->json('promosi_bts_video_urls')->nullable();
            $table->json('promosi_talent_photo_urls')->nullable();
            $table->text('promosi_bts_notes')->nullable();
            $table->timestamp('promosi_bts_completed_at')->nullable();
            $table->foreignId('promosi_bts_completed_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Highlight Content (Tahap 2)
            $table->json('promosi_ig_story_urls')->nullable();
            $table->json('promosi_fb_reel_urls')->nullable();
            $table->text('promosi_highlight_notes')->nullable();
            $table->timestamp('promosi_highlight_completed_at')->nullable();
            $table->foreignId('promosi_highlight_completed_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Social Media Shares
            $table->json('promosi_social_shares')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('program_episodes')) {
            return;
        }

        Schema::table('program_episodes', function (Blueprint $table) {
            // Drop all workflow fields
            $table->dropColumn([
                // Creative
                'script_submitted_at', 'script_submitted_by',
                
                // Producer review
                'rundown_approved_at', 'rundown_approved_by', 'rundown_rejected_at',
                'rundown_rejected_by', 'rundown_rejection_notes', 'rundown_revision_points',
                'producer_notes',
                
                // Produksi
                'raw_file_urls', 'shooting_notes', 'actual_shooting_date',
                'shooting_completed_at', 'shooting_completed_by', 'budget_talent',
                
                // Editor
                'editing_status', 'editing_started_at', 'editing_started_by', 'editing_notes',
                'editing_drafts', 'final_file_url', 'editing_completion_notes',
                'edited_duration_minutes', 'final_file_size_mb', 'editing_completed_at',
                'editing_completed_by', 'editing_revisions', 'revision_acknowledged_at',
                'revision_acknowledged_by',
                
                // QC
                'qc_approved_at', 'qc_approved_by', 'qc_revision_requested_at',
                'qc_revision_requested_by', 'qc_revision_count',
                
                // Broadcasting
                'seo_title', 'seo_description', 'seo_tags', 'youtube_category',
                'youtube_privacy', 'metadata_updated_at', 'metadata_updated_by',
                'youtube_url', 'youtube_video_id', 'youtube_upload_status',
                'youtube_upload_started_at', 'youtube_uploaded_at', 'youtube_upload_by',
                'website_url', 'website_published_at', 'website_published_by',
                'broadcast_notes', 'actual_air_date', 'broadcast_completed_at',
                'broadcast_completed_by',
                
                // Design Grafis
                'thumbnail_youtube', 'thumbnail_bts', 'design_assets_talent_photos',
                'design_assets_bts_photos', 'design_assets_production_files',
                'design_assets_received_at', 'design_assets_received_by', 'design_assets_notes',
                'thumbnail_youtube_uploaded_at', 'thumbnail_youtube_uploaded_by',
                'thumbnail_youtube_notes', 'thumbnail_bts_uploaded_at',
                'thumbnail_bts_uploaded_by', 'thumbnail_bts_notes', 'design_completed_at',
                'design_completed_by', 'design_completion_notes',
                
                // Promosi
                'promosi_bts_video_urls', 'promosi_talent_photo_urls', 'promosi_bts_notes',
                'promosi_bts_completed_at', 'promosi_bts_completed_by',
                'promosi_ig_story_urls', 'promosi_fb_reel_urls', 'promosi_highlight_notes',
                'promosi_highlight_completed_at', 'promosi_highlight_completed_by',
                'promosi_social_shares'
            ]);
        });
    }
};
