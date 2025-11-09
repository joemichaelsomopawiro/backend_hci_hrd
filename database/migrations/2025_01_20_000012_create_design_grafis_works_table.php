<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Design Grafis Works table - Graphics and design work
     */
    public function up(): void
    {
        Schema::create('design_grafis_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
            $table->enum('work_type', [
                'thumbnail_youtube',  // Thumbnail YouTube
                'thumbnail_bts',     // Thumbnail BTS
                'graphics_ig',       // Graphics Instagram
                'graphics_facebook', // Graphics Facebook
                'banner_website'     // Banner Website
            ]);
            $table->string('title'); // Judul design
            $table->text('description')->nullable(); // Deskripsi
            $table->text('design_brief')->nullable(); // Brief design
            $table->text('brand_guidelines')->nullable(); // Panduan brand
            $table->string('color_scheme')->nullable(); // Skema warna
            $table->string('dimensions')->nullable(); // Dimensi
            $table->string('file_format')->nullable(); // Format file
            $table->date('deadline')->nullable(); // Deadline
            $table->text('design_notes')->nullable(); // Catatan design
            $table->string('file_path')->nullable(); // Path to design file
            $table->string('file_name')->nullable(); // Original file name
            $table->bigInteger('file_size')->nullable(); // File size in bytes
            $table->string('mime_type')->nullable(); // File MIME type
            $table->json('file_paths')->nullable(); // Multiple file paths
            $table->enum('status', [
                'draft',           // Draft
                'in_progress',     // Sedang dikerjakan
                'completed',       // Design selesai
                'reviewed',        // Sudah direview
                'approved'         // Sudah diapprove
            ])->default('draft');
            
            // Design Information
            $table->json('source_files')->nullable(); // File sumber yang digunakan
            $table->text('design_specifications')->nullable(); // Spesifikasi design
            $table->string('platform')->nullable(); // Platform target (YouTube, Instagram, etc.)
            
            // Workflow Information
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('qc_feedback')->nullable(); // Feedback dari QC
            
            $table->timestamps();
            
            // Indexes
            $table->index('episode_id');
            $table->index('work_type');
            $table->index('status');
            $table->index('created_by');
            $table->index('reviewed_by');
            $table->index('platform');
            $table->index(['episode_id', 'work_type']);
            $table->index(['episode_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('design_grafis_works');
    }
};

