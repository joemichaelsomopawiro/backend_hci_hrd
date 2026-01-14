<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * History Revisi Program - tracking semua revisi
     */
    public function up(): void
    {
        Schema::create('pr_program_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('pr_programs')->onDelete('cascade');
            
            // Tipe revisi
            $table->enum('revision_type', [
                'concept',           // Revisi konsep
                'production',        // Revisi produksi
                'editing',           // Revisi editing
                'distribution'       // Revisi distribusi
            ]);
            
            // Data sebelum revisi (snapshot)
            $table->json('before_data')->nullable(); // Data sebelum revisi
            
            // Data setelah revisi
            $table->json('after_data')->nullable(); // Data setelah revisi
            
            // Alasan revisi
            $table->text('revision_reason'); // Alasan revisi
            
            // Status revisi
            $table->enum('status', [
                'pending',           // Menunggu approval
                'approved',          // Disetujui
                'rejected'           // Ditolak
            ])->default('pending');
            
            // Siapa yang meminta revisi
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            
            // Siapa yang approve/reject revisi
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            
            $table->timestamps();
            
            $table->index(['program_id', 'revision_type', 'status']);
            $table->index(['requested_by', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_program_revisions');
    }
};
