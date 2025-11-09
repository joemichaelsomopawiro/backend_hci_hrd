<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Editor Works table - Video editing work
     */
    public function up(): void
    {
        Schema::create('editor_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
            $table->enum('work_type', [
                'main_episode',     // Edit episode utama
                'bts',              // Edit BTS
                'highlight_ig',    // Highlight Instagram
                'highlight_tv',     // Highlight TV
                'highlight_facebook', // Highlight Facebook
                'advertisement'     // Iklan
            ]);
            $table->text('editing_notes')->nullable(); // Catatan editing
            $table->string('file_path')->nullable(); // Path to edited file
            $table->string('file_name')->nullable(); // Original file name
            $table->bigInteger('file_size')->nullable(); // File size in bytes
            $table->string('mime_type')->nullable(); // File MIME type
            $table->enum('status', [
                'draft',           // Draft
                'editing',         // Sedang editing
                'completed',       // Editing selesai
                'reviewed',        // Sudah direview
                'approved'         // Sudah diapprove
            ])->default('draft');
            
            // File Information
            $table->json('source_files')->nullable(); // File sumber yang digunakan
            $table->text('file_notes')->nullable(); // Catatan file
            $table->boolean('file_complete')->default(false); // Status kelengkapan file
            
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
            $table->index(['episode_id', 'work_type']);
            $table->index(['episode_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('editor_works');
    }
};














