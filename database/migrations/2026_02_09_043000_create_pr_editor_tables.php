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
        // Table for Editor works
        if (!Schema::hasTable('pr_editor_works')) {
            Schema::create('pr_editor_works', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pr_episode_id')->constrained('pr_episodes')->onDelete('cascade');
                $table->foreignId('pr_production_work_id')->constrained('pr_produksi_works')->onDelete('cascade');
                $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
                $table->enum('status', [
                    'pending',
                    'checking_files',
                    'in_progress',
                    'waiting_producer_approval',
                    'completed'
                ])->default('pending');
                $table->boolean('files_complete')->default(false);
                $table->text('edited_video_link')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        // Table for Editor revision notes
        if (!Schema::hasTable('pr_editor_revision_notes')) {
            Schema::create('pr_editor_revision_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pr_editor_work_id')->constrained('pr_editor_works')->onDelete('cascade');
                $table->foreignId('pr_episode_id')->constrained('pr_episodes')->onDelete('cascade');
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
                $table->text('notes');
                $table->enum('status', [
                    'pending',
                    'approved_by_producer',
                    'sent_to_production'
                ])->default('pending');
                $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }

        // Table for Editor Promosi works
        if (!Schema::hasTable('pr_editor_promosi_works')) {
            Schema::create('pr_editor_promosi_works', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pr_episode_id')->constrained('pr_episodes')->onDelete('cascade');
                $table->foreignId('pr_editor_work_id')->nullable()->constrained('pr_editor_works')->onDelete('cascade');
                $table->foreignId('pr_promotion_work_id')->constrained('pr_promotion_works')->onDelete('cascade');
                $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
                $table->enum('status', [
                    'pending',
                    'waiting_editor',
                    'in_progress',
                    'completed'
                ])->default('pending');
                $table->text('bts_video_link')->nullable();
                $table->text('tv_ad_link')->nullable();
                $table->text('ig_highlight_link')->nullable();
                $table->text('tv_highlight_link')->nullable();
                $table->text('fb_highlight_link')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        // Table for Design Grafis works
        if (!Schema::hasTable('pr_design_grafis_works')) {
            Schema::create('pr_design_grafis_works', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pr_episode_id')->constrained('pr_episodes')->onDelete('cascade');
                $table->foreignId('pr_production_work_id')->constrained('pr_produksi_works')->onDelete('cascade');
                $table->foreignId('pr_promotion_work_id')->constrained('pr_promotion_works')->onDelete('cascade');
                $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
                $table->enum('status', [
                    'pending',
                    'in_progress',
                    'completed'
                ])->default('pending');
                $table->text('youtube_thumbnail_link')->nullable();
                $table->text('bts_thumbnail_link')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_design_grafis_works');
        Schema::dropIfExists('pr_editor_promosi_works');
        Schema::dropIfExists('pr_editor_revision_notes');
        Schema::dropIfExists('pr_editor_works');
    }
};
