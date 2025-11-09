<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creative Works table - Creative work (script, storyboard, budget)
     */
    public function up(): void
    {
        Schema::create('creative_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
            
            // Creative Content
            $table->text('script_content')->nullable(); // Script cerita video klip
            $table->json('storyboard_data')->nullable(); // Storyboard data
            $table->json('budget_data')->nullable(); // Budget data (talent, equipment, etc.)
            
            // Schedule Information
            $table->datetime('recording_schedule')->nullable(); // Jadwal rekaman suara
            $table->datetime('shooting_schedule')->nullable(); // Jadwal syuting
            $table->string('shooting_location')->nullable(); // Lokasi syuting
            
            // Status
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
            $table->index('recording_schedule');
            $table->index('shooting_schedule');
            $table->index(['episode_id', 'status']);
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














