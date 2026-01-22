<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Produksi Works table untuk Program Regular
     * Menangani equipment requests, syuting, dan run sheet
     */
    public function up(): void
    {
        Schema::create('pr_produksi_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pr_episode_id')->constrained('pr_episodes')->onDelete('cascade');
            $table->foreignId('pr_creative_work_id')->nullable()->constrained('pr_creative_works')->onDelete('set null');

            // Equipment Management
            $table->json('equipment_list')->nullable(); // List alat yang diminta
            $table->json('equipment_requests')->nullable(); // IDs dari ProductionEquipment requests
            $table->json('needs_list')->nullable(); // Kebutuhan lain (non-equipment)
            $table->json('needs_requests')->nullable(); // Request metadata

            // Run Sheet
            $table->foreignId('run_sheet_id')->nullable()->constrained('shooting_run_sheets')->onDelete('set null');

            // Shooting Results
            $table->json('shooting_files')->nullable(); // Uploaded file metadata
            $table->text('shooting_file_links')->nullable(); // Comma-separated file paths
            $table->text('shooting_notes')->nullable();

            // Status
            $table->enum('status', [
                'pending',         // Menunggu Produksi terima
                'in_progress',     // Produksi sedang mengerjakan (request equipment, dll)
                'equipment_requested', // Sudah request equipment
                'ready_to_shoot',  // Equipment approved, siap syuting
                'shooting',        // Sedang syuting
                'completed'        // Selesai, files uploaded
            ])->default('pending');

            // Metadata
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('completion_notes')->nullable();
            $table->timestamp('completed_at')->nullable();

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
        Schema::dropIfExists('pr_produksi_works');
    }
};
