<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Quality Controls table - Quality control management
     */
    public function up(): void
    {
        Schema::create('quality_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
            $table->enum('qc_type', [
                'video_bts',        // QC video BTS
                'advertisement_tv', // QC iklan episode TV
                'highlight_ig',     // QC highlight episode IG
                'highlight_tv',     // QC highlight episode TV
                'highlight_facebook', // QC highlight episode Facebook
                'thumbnail_yt',     // QC thumbnail YouTube
                'thumbnail_bts',    // QC thumbnail BTS
                'main_episode'      // QC episode utama
            ]);
            $table->enum('status', [
                'pending',          // Menunggu QC
                'in_progress',      // Sedang QC
                'completed',        // QC selesai
                'approved',         // QC approved
                'rejected'          // QC rejected
            ])->default('pending');
            
            // QC Information
            $table->text('qc_notes')->nullable(); // Catatan QC
            $table->text('feedback')->nullable(); // Feedback QC
            $table->json('qc_checklist')->nullable(); // Checklist QC
            $table->integer('quality_score')->nullable(); // Skor kualitas (1-10)
            $table->json('improvement_areas')->nullable(); // Area yang perlu diperbaiki
            
            // File Information
            $table->string('file_path')->nullable(); // Path file yang di-QC
            $table->string('file_name')->nullable(); // Nama file
            $table->bigInteger('file_size')->nullable(); // Ukuran file
            $table->string('mime_type')->nullable(); // MIME type
            
            // Workflow Information
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('qc_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('qc_started_at')->nullable();
            $table->timestamp('qc_completed_at')->nullable();
            $table->text('qc_result_notes')->nullable(); // Catatan hasil QC
            
            $table->timestamps();
            
            // Indexes
            $table->index('episode_id');
            $table->index('qc_type');
            $table->index('status');
            $table->index('created_by');
            $table->index('qc_by');
            $table->index('quality_score');
            $table->index(['episode_id', 'qc_type']);
            $table->index(['episode_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_controls');
    }
};














