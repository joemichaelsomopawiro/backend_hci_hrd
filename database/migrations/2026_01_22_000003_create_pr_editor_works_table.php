<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Editor Works table untuk Program Regular
     * Menangani editing video untuk berbagai tipe konten
     */
    public function up(): void
    {
        Schema::create('pr_editor_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pr_episode_id')->constrained('pr_episodes')->onDelete('cascade');

            // Work Type
            $table->enum('work_type', [
                'main_episode',        // Episode utama
                'bts',                 // Behind the scenes
                'highlight_ig',        // Highlight untuk Instagram
                'highlight_tv',        // Highlight untuk TV
                'highlight_facebook',  // Highlight untuk Facebook
                'advertisement'        // Iklan
            ])->default('main_episode');

            // Source Files (dari Produksi)
            $table->json('source_files')->nullable(); // Source files metadata
            $table->boolean('file_complete')->default(false); // Apakah file lengkap?
            $table->text('file_notes')->nullable(); // Notes tentang file

            // Editing
            $table->text('editing_notes')->nullable();
            $table->string('file_path')->nullable(); // Path hasil editing
            $table->string('file_name')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();

            // Status
            $table->enum('status', [
                'draft',           // Baru dibuat
                'editing',         // Sedang editing
                'pending_review',  // Menunggu review Manager Program
                'approved',        // Disetujui
                'rejected',        // Ditolak, perlu re-edit
                'completed'        // Selesai final
            ])->default('draft');

            // Metadata
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['pr_episode_id', 'work_type']);
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
        Schema::dropIfExists('pr_editor_works');
    }
};
