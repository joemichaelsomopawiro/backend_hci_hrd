<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Creative Works table untuk Program Regular
     * Menangani script, storyboard, budget, dan jadwal syuting
     */
    public function up(): void
    {
        Schema::create('pr_creative_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pr_episode_id')->constrained('pr_episodes')->onDelete('cascade');

            // Content Fields
            $table->text('script_content')->nullable(); // Script cerita
            $table->json('storyboard_data')->nullable(); // Storyboard data
            $table->json('budget_data')->nullable(); // Budget breakdown

            // Jadwal
            $table->dateTime('recording_schedule')->nullable(); // Jadwal recording (jika ada)
            $table->dateTime('shooting_schedule')->nullable(); // Jadwal syuting
            $table->string('shooting_location')->nullable(); // Lokasi syuting

            // Approval Status - Producer & Manager Program dapat approve terpisah
            $table->boolean('script_approved')->nullable(); // Producer approval untuk script
            $table->text('script_review_notes')->nullable();
            $table->foreignId('script_approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('script_approved_at')->nullable();

            $table->boolean('storyboard_approved')->nullable(); // Producer approval untuk storyboard
            $table->text('storyboard_review_notes')->nullable();
            $table->foreignId('storyboard_approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('storyboard_approved_at')->nullable();

            $table->boolean('budget_approved')->nullable(); // Producer approval untuk budget normal
            $table->text('budget_review_notes')->nullable();
            $table->foreignId('budget_approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('budget_approved_at')->nullable();

            // Special budget approval (Manager Program)
            $table->boolean('requires_special_budget_approval')->default(false);
            $table->text('special_budget_reason')->nullable();
            $table->foreignId('special_budget_approval_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('special_budget_approved_at')->nullable();

            // Status
            $table->enum('status', [
                'draft',           // Baru dibuat, belum dikerjakan
                'in_progress',     // Creative sedang mengerjakan
                'submitted',       // Sudah submit ke Producer untuk review
                'approved',        // Disetujui Producer (dan Manager Program jika perlu)
                'rejected',        // Ditolak, perlu revisi
                'revised'          // Sedang revisi
            ])->default('draft');

            // Metadata
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('review_notes')->nullable(); // General review notes
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['pr_episode_id', 'status']);
            $table->index('created_by');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_creative_works');
    }
};
