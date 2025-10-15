<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Budgets - Budget management untuk production
     * Dibuat oleh Kreatif, direview oleh Producer, approval khusus oleh Manager Program
     */
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_submission_id')->constrained('music_submissions')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            
            // Budget Breakdown
            $table->decimal('talent_budget', 15, 2)->default(0); // Budget untuk talent/penyanyi
            $table->decimal('production_budget', 15, 2)->default(0); // Budget untuk produksi
            $table->decimal('other_budget', 15, 2)->default(0); // Budget lain-lain
            $table->decimal('total_budget', 15, 2)->default(0); // Total budget (auto-calculated)
            
            // Budget Notes
            $table->text('budget_notes')->nullable();
            $table->text('talent_budget_notes')->nullable();
            $table->text('production_budget_notes')->nullable();
            $table->text('other_budget_notes')->nullable();
            
            // Status Workflow
            $table->enum('status', [
                'draft',                      // Masih draft
                'submitted',                  // Sudah submit ke Producer
                'under_review',               // Sedang direview Producer
                'approved',                   // Diapprove Producer (budget normal)
                'pending_special_approval',   // Menunggu approval Manager Program (budget khusus)
                'special_approved',           // Budget khusus diapprove Manager Program
                'rejected',                   // Ditolak
                'revision'                    // Perlu revisi
            ])->default('draft');
            
            // Approval Tracking
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('review_notes')->nullable();
            
            // Special Budget Approval (jika > standard)
            $table->boolean('requires_special_approval')->default(false);
            $table->decimal('standard_budget_limit', 15, 2)->nullable(); // Limit budget normal
            
            $table->timestamps();
            
            $table->index(['music_submission_id', 'status']);
            $table->index(['created_by', 'status']);
            $table->index(['requires_special_approval', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};






