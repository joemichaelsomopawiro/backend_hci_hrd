<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Quality Control Works table untuk Program Regular
     * Menangani QC untuk berbagai konten sebelum publish
     */
    public function up(): void
    {
        Schema::create('pr_quality_control_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pr_episode_id')->constrained('pr_episodes')->onDelete('cascade');

            // QC Type
            $table->enum('qc_type', [
                'episode',             // QC episode utama
                'bts',                 // QC BTS video
                'highlight',           // QC highlight videos
                'thumbnail',           // QC thumbnails
                'promotional_content', // QC konten promosi
                'all'                  // QC menyeluruh semua konten
            ])->default('all');

            // File Locations (from Editor Promosi & Design Grafis)
            $table->json('editor_promosi_file_locations')->nullable();
            $table->json('design_grafis_file_locations')->nullable();

            // QC Checklist - Detailed per item
            $table->json('qc_checklist')->nullable(); // BTS, iklan TV, highlight IG, TV, FB, thumbnail YT, thumbnail BTS

            // QC Results
            $table->json('qc_results')->nullable(); // Hasil QC per konten
            $table->integer('quality_score')->nullable(); // Overall score 1-100
            $table->text('qc_notes')->nullable();
            $table->json('issues_found')->nullable(); // Issues yang ditemukan
            $table->json('improvements_needed')->nullable(); // Improvement yang diperlukan

            // Screenshots (untuk dokumentasi QC)
            $table->json('screenshots')->nullable();

            // Status
            $table->enum('status', [
                'pending',         // Menunggu QC
                'in_progress',     // QC sedang berjalan
                'completed',       // QC selesai
                'approved',        // Disetujui, siap publish
                'rejected'         // Ditolak, perlu perbaikan
            ])->default('pending');

            // Metadata
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('qc_completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['pr_episode_id', 'qc_type']);
            $table->index(['pr_episode_id', 'status']);
            $table->index('reviewed_by');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_quality_control_works');
    }
};
