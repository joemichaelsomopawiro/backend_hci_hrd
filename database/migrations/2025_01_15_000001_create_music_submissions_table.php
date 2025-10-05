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
        Schema::create('music_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('music_arranger_id');
            $table->unsignedBigInteger('song_id');
            $table->unsignedBigInteger('proposed_singer_id')->nullable();
            $table->text('arrangement_notes')->nullable();
            $table->date('requested_date')->nullable();
            
            // Workflow data
            $table->enum('current_state', [
                'submitted', 'producer_review', 'arranging', 'arrangement_review',
                'sound_engineering', 'quality_control', 'creative_work', 
                'final_approval', 'completed', 'rejected'
            ])->default('submitted');
            
            // Final decisions
            $table->unsignedBigInteger('approved_singer_id')->nullable();
            $table->text('producer_notes')->nullable();
            $table->text('final_approval_notes')->nullable();
            
            // Creative work data
            $table->text('script_content')->nullable();
            $table->json('storyboard_data')->nullable();
            $table->datetime('recording_schedule')->nullable();
            $table->datetime('shooting_schedule')->nullable();
            $table->string('shooting_location')->nullable();
            $table->json('budget_data')->nullable();
            
            // Arrangement data
            $table->string('arrangement_file_path')->nullable();
            $table->string('arrangement_file_url')->nullable();
            
            // Sound engineering data
            $table->string('processed_audio_path')->nullable();
            $table->string('processed_audio_url')->nullable();
            $table->text('sound_engineering_notes')->nullable();
            
            // Quality control data
            $table->text('quality_control_notes')->nullable();
            $table->boolean('quality_control_approved')->default(false);
            
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('music_arranger_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('song_id')->references('id')->on('songs')->onDelete('cascade');
            $table->foreign('proposed_singer_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_singer_id')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index('current_state');
            $table->index('music_arranger_id');
            $table->index('song_id');
            $table->index(['current_state', 'music_arranger_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_submissions');
    }
};






