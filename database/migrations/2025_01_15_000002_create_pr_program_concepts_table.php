<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Konsep Program - dibuat oleh Manager Program
     */
    public function up(): void
    {
        Schema::create('pr_program_concepts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('pr_programs')->onDelete('cascade');
            
            // Konsep Program (field sesuai flowchart)
            $table->text('concept'); // Konsep program
            $table->text('objectives')->nullable(); // Tujuan program
            $table->text('target_audience')->nullable(); // Target audience
            $table->text('content_outline')->nullable(); // Outline konten
            $table->text('format_description')->nullable(); // Deskripsi format
            
            // Status Konsep
            $table->enum('status', [
                'draft',              // Draft
                'pending_approval',   // Menunggu approval Producer
                'approved',           // Disetujui Producer
                'rejected',           // Ditolak Producer
                'revised'             // Direvisi
            ])->default('draft');
            
            // Approval tracking
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_notes')->nullable();
            
            // Created by (Manager Program)
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['program_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_program_concepts');
    }
};
