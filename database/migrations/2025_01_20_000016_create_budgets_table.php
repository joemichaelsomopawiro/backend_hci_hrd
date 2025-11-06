<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Budgets table - Budget management
     */
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
            $table->enum('budget_type', [
                'talent_fee',       // Bayar talent
                'equipment_rental',  // Sewa alat
                'location_fee',      // Biaya lokasi
                'transportation',    // Transportasi
                'food_catering',     // Makanan
                'special_request'    // Permintaan khusus
            ]);
            $table->decimal('amount', 15, 2); // Jumlah budget
            $table->text('description')->nullable(); // Deskripsi budget
            $table->enum('status', [
                'draft',            // Draft
                'submitted',        // Submitted for approval
                'approved',         // Approved
                'rejected',         // Rejected
                'revised'           // Revised
            ])->default('draft');
            
            // Approval Information
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('requested_at')->useCurrent();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('rejected_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Usage Information
            $table->decimal('used_amount', 15, 2)->default(0); // Jumlah yang sudah digunakan
            $table->decimal('remaining_amount', 15, 2)->nullable(); // Jumlah yang tersisa
            $table->timestamp('used_at')->nullable(); // Tanggal penggunaan
            
            $table->timestamps();
            
            // Indexes
            $table->index('episode_id');
            $table->index('budget_type');
            $table->index('status');
            $table->index('requested_by');
            $table->index('approved_by');
            $table->index('requested_at');
            $table->index(['episode_id', 'budget_type']);
            $table->index(['episode_id', 'status']);
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














