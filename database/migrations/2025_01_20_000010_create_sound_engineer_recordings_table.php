<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Sound Engineer Recordings table - Recording work
     */
    public function up(): void
    {
        Schema::create('sound_engineer_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
            $table->text('recording_notes')->nullable(); // Catatan rekaman
            $table->string('file_path')->nullable(); // Path to recording file
            $table->string('file_name')->nullable(); // Original file name
            $table->bigInteger('file_size')->nullable(); // File size in bytes
            $table->string('mime_type')->nullable(); // File MIME type
            $table->json('equipment_used')->nullable(); // Equipment yang digunakan
            $table->enum('status', [
                'draft',           // Draft
                'recording',       // Sedang rekaman
                'completed',       // Rekaman selesai
                'reviewed'         // Sudah direview
            ])->default('draft');
            
            // Schedule Information
            $table->datetime('recording_schedule')->nullable(); // Jadwal rekaman
            $table->timestamp('recording_started_at')->nullable(); // Mulai rekaman
            $table->timestamp('recording_completed_at')->nullable(); // Selesai rekaman
            
            // Workflow Information
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('episode_id');
            $table->index('status');
            $table->index('created_by');
            $table->index('reviewed_by');
            $table->index('recording_schedule');
            $table->index(['episode_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sound_engineer_recordings');
    }
};














