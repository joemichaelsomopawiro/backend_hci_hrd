<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Promotion Materials table - Promotion and BTS materials
     */
    public function up(): void
    {
        Schema::create('promotion_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
            $table->enum('material_type', [
                'bts_video',        // Video BTS
                'bts_photo',        // Foto BTS
                'highlight_ig',     // Highlight Instagram
                'highlight_facebook', // Highlight Facebook
                'highlight_tv',     // Highlight TV
                'advertisement'     // Iklan
            ]);
            $table->text('material_notes')->nullable(); // Catatan material
            $table->string('file_path')->nullable(); // Path to material file
            $table->string('file_name')->nullable(); // Original file name
            $table->bigInteger('file_size')->nullable(); // File size in bytes
            $table->string('mime_type')->nullable(); // File MIME type
            $table->enum('status', [
                'draft',           // Draft
                'creating',        // Sedang membuat
                'completed',       // Material selesai
                'reviewed',        // Sudah direview
                'approved'         // Sudah diapprove
            ])->default('draft');
            
            // Platform Information
            $table->string('platform')->nullable(); // Platform target
            $table->text('platform_notes')->nullable(); // Catatan platform
            $table->json('social_media_links')->nullable(); // Link ke media sosial
            
            // Workflow Information
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('qc_feedback')->nullable(); // Feedback dari QC
            
            $table->timestamps();
            
            // Indexes
            $table->index('episode_id');
            $table->index('material_type');
            $table->index('status');
            $table->index('created_by');
            $table->index('reviewed_by');
            $table->index('platform');
            $table->index(['episode_id', 'material_type']);
            $table->index(['episode_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_materials');
    }
};














