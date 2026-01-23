<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('editor_promosi_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained()->onDelete('cascade');
            
            // Work type: bts_video, iklan_tv, highlight_ig, highlight_tv, highlight_facebook, teaser, trailer
            $table->string('work_type');
            
            // External storage links (JSON array)
            $table->json('file_links')->nullable();
            
            // Status tracking
            $table->string('status')->default('draft'); // draft, in_progress, submitted, in_qc, approved, rejected
            
            // User assignments
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            
            // Review information
            $table->text('review_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            
            // Task reassignment fields (for auto-revert logic)
            $table->foreignId('originally_assigned_to')->nullable()->constrained('users');
            $table->boolean('was_reassigned')->default(false);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['episode_id', 'work_type']);
            $table->index('status');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('editor_promosi_works');
    }
};
