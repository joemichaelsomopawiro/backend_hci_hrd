<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pr_manager_distribusi_qc_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pr_episode_id')->constrained('pr_episodes')->onDelete('cascade');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'approved', 'rejected'])->default('pending');
            $table->json('qc_checklist')->nullable();

            // Allow tracking of notes and scores
            $table->text('qc_results')->nullable();
            $table->integer('quality_score')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamp('qc_completed_at')->nullable();
            $table->timestamp('recieved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['pr_episode_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_manager_distribusi_qc_works');
    }
};
