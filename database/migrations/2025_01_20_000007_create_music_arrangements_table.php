<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Music Arrangements table - Music arrangement work
     */
    public function up(): void
    {
        Schema::create('music_arrangements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
            $table->string('song_title');
            $table->string('singer_name')->nullable();
            $table->text('arrangement_notes')->nullable();
            $table->string('file_path')->nullable(); // Path to arrangement file
            $table->string('file_name')->nullable(); // Original file name
            $table->bigInteger('file_size')->nullable(); // File size in bytes
            $table->string('mime_type')->nullable(); // File MIME type
            $table->enum('status', [
                'draft',           // Draft
                'submitted',       // Submitted for review
                'approved',        // Approved by producer
                'rejected',        // Rejected by producer
                'revised'          // Revised after rejection
            ])->default('draft');
            
            // Workflow Information
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('episode_id');
            $table->index('status');
            $table->index('created_by');
            $table->index('reviewed_by');
            $table->index(['episode_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_arrangements');
    }
};














