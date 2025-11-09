<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Workflow States table - Track workflow states untuk setiap episode
     */
    public function up(): void
    {
        Schema::create('workflow_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
            $table->enum('current_state', [
                'program_created',
                'episode_generated',
                'music_arrangement',
                'creative_work',
                'production_planning',
                'equipment_request',
                'shooting_recording',
                'editing',
                'quality_control',
                'broadcasting',
                'promotion',
                'completed'
            ]);
            $table->string('assigned_to_role', 50); // Role yang bertanggung jawab
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->onDelete('set null'); // User yang bertanggung jawab
            $table->text('notes')->nullable(); // Catatan workflow
            $table->json('metadata')->nullable(); // Data tambahan workflow
            
            $table->timestamps();
            
            // Indexes
            $table->index('episode_id');
            $table->index('current_state');
            $table->index('assigned_to_role');
            $table->index('assigned_to_user_id');
            $table->index(['episode_id', 'current_state']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_states');
    }
};














