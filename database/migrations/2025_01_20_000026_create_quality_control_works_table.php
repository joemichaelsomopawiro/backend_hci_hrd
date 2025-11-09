<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Quality Control Works - Untuk tim Quality Control
     */
    public function up(): void
    {
        Schema::create('quality_control_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // Quality Control
            $table->enum('qc_type', [
                'main_episode',        // QC episode utama
                'bts_video',           // QC video BTS
                'highlight_ig',        // QC highlight Instagram
                'highlight_facebook',  // QC highlight Facebook
                'highlight_tv',        // QC highlight TV
                'thumbnail_yt',        // QC thumbnail YouTube
                'thumbnail_bts',       // QC thumbnail BTS
                'advertisement_tv'     // QC iklan TV
            ]);
            $table->string('title'); // Judul QC
            $table->text('description')->nullable(); // Deskripsi
            $table->json('files_to_check')->nullable(); // File yang perlu dicek
            $table->json('qc_checklist')->nullable(); // Checklist QC
            $table->json('quality_standards')->nullable(); // Standar kualitas
            $table->integer('quality_score')->nullable(); // Skor kualitas (1-100)
            $table->json('issues_found')->nullable(); // Masalah yang ditemukan
            $table->json('improvements_needed')->nullable(); // Perbaikan yang diperlukan
            $table->text('qc_notes')->nullable(); // Catatan QC
            $table->json('screenshots')->nullable(); // Screenshot masalah
            $table->enum('status', [
                'pending',          // Menunggu QC
                'in_progress',      // Sedang QC
                'passed',           // Lulus QC
                'failed',           // Gagal QC
                'revision_needed',  // Perlu revisi
                'approved'          // Disetujui
            ])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('episode_id');
            $table->index('qc_type');
            $table->index('status');
            $table->index('quality_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_control_works');
    }
};













