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
        Schema::create('episode_qc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_episode_id')->constrained('program_episodes')->onDelete('cascade');
            $table->foreignId('qc_by')->constrained('users')->onDelete('cascade');
            $table->enum('decision', ['approved', 'revision_needed']);
            $table->integer('quality_score'); // 1-10
            $table->integer('video_quality_score')->nullable(); // 1-10
            $table->integer('audio_quality_score')->nullable(); // 1-10
            $table->integer('content_quality_score')->nullable(); // 1-10
            $table->text('notes');
            $table->json('revision_points')->nullable();
            $table->timestamp('reviewed_at');
            $table->enum('status', ['approved', 'revision_needed', 'completed'])->default('approved');
            $table->timestamps();

            // Indexes
            $table->index('program_episode_id');
            $table->index('qc_by');
            $table->index('decision');
            $table->index('reviewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('episode_qc');
    }
};
