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
        Schema::create('pr_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pr_episode_id')->constrained('pr_episodes')->onDelete('cascade');
            $table->integer('step_number'); // 1-8 representing the workflow steps
            $table->string('step_name'); // e.g., "Kreatif", "Produksi", etc.
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('completion_notes')->nullable();
            $table->timestamps();

            // Unique constraint: one record per episode per step
            $table->unique(['pr_episode_id', 'step_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_workflow_steps');
    }
};
