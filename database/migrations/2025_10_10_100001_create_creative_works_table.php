<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creative Works - Script & Storyboard untuk music video production
     * Dibuat oleh role Kreatif setelah music arrangement approved
     */
    public function up(): void
    {
        Schema::create('creative_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_submission_id')->constrained('music_submissions')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            
            // Script Content
            $table->text('script_content')->nullable();
            
            // Storyboard File
            $table->string('storyboard_file_path')->nullable();
            $table->string('storyboard_file_name')->nullable();
            $table->integer('storyboard_file_size')->nullable(); // in bytes
            
            // Notes
            $table->text('creative_notes')->nullable();
            
            // Status Workflow
            $table->enum('status', [
                'draft',           // Masih draft
                'submitted',       // Sudah submit ke Producer
                'under_review',    // Sedang direview Producer
                'approved',        // Diapprove Producer
                'rejected',        // Ditolak Producer
                'revision'         // Perlu revisi
            ])->default('draft');
            
            // Timestamps & Review
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('review_notes')->nullable();
            
            // Approval flags (Producer dapat approve script & storyboard terpisah)
            $table->boolean('script_approved')->default(false);
            $table->boolean('storyboard_approved')->default(false);
            
            $table->timestamps();
            
            $table->index(['music_submission_id', 'status']);
            $table->index(['created_by', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creative_works');
    }
};







