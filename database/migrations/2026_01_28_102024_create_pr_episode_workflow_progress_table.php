<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Workflow Progress Tracking untuk Episode Program Regular
     * Setiap episode memiliki 10 workflow steps yang bisa ditrack progressnya
     */
    public function up(): void
    {
        Schema::create('pr_episode_workflow_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('pr_episodes')->onDelete('cascade');

            // Workflow Step Information
            $table->integer('workflow_step'); // 1-10
            $table->string('step_name'); // Nama kegiatan
            $table->string('responsible_role'); // Role yang bertanggung jawab

            // Assignment
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->onDelete('set null');

            // Status
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');

            // Timestamps
            $table->timestamp('started_at')->nullable(); // Kapan step dimulai
            $table->timestamp('completed_at')->nullable(); // Kapan step selesai

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->unique(['episode_id', 'workflow_step']); // Satu episode tidak boleh punya duplicate step
            $table->index(['episode_id', 'status']);
            $table->index(['workflow_step', 'status']);
            $table->index('assigned_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_episode_workflow_progress');
    }
};
