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
        Schema::create('music_workflow_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('submission_id');
            $table->enum('current_state', [
                'submitted', 'producer_review', 'arranging', 'arrangement_review',
                'sound_engineering', 'quality_control', 'creative_work', 
                'final_approval', 'completed', 'rejected'
            ]);
            $table->string('assigned_to_role', 50);
            $table->unsignedBigInteger('assigned_to_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('submission_id')->references('id')->on('music_submissions')->onDelete('cascade');
            $table->foreign('assigned_to_user_id')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index('submission_id');
            $table->index('current_state');
            $table->index('assigned_to_role');
            $table->index('assigned_to_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_workflow_states');
    }
};






