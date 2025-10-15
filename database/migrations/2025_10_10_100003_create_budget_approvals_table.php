<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Budget Approvals - Tracking special budget approval oleh Manager Program
     * Hanya digunakan jika budget > standard limit
     */
    public function up(): void
    {
        Schema::create('budget_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->onDelete('cascade');
            $table->foreignId('music_submission_id')->constrained('music_submissions')->onDelete('cascade');
            
            // Request Info
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade'); // Producer
            $table->decimal('requested_amount', 15, 2); // Total budget yang direquest
            $table->text('request_reason')->nullable(); // Alasan butuh budget khusus
            
            // Approval Info
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null'); // Manager Program
            $table->decimal('approved_amount', 15, 2)->nullable(); // Budget yang diapprove (bisa beda dari request)
            $table->text('approval_notes')->nullable();
            
            // Status
            $table->enum('status', [
                'pending',      // Menunggu approval
                'approved',     // Diapprove
                'rejected',     // Ditolak
                'revised'       // Direvisi amount-nya
            ])->default('pending');
            
            // Timestamps
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['budget_id', 'status']);
            $table->index(['music_submission_id', 'status']);
            $table->index(['requested_by', 'status']);
            $table->index(['approved_by', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_approvals');
    }
};






