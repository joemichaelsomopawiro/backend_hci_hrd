<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('program_approvals', function (Blueprint $table) {
            $table->id();
            
            // Polymorphic relationship untuk entity yang perlu approval
            $table->string('approvable_type'); // App\Models\Episode, App\Models\CreativeWork, dll
            $table->unsignedBigInteger('approvable_id');
            
            // Approval type
            $table->string('approval_type'); // 'episode_rundown', 'special_budget', 'schedule_cancellation', 'schedule_change', 'rundown_edit'
            
            // Request info
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->text('request_notes')->nullable();
            $table->json('request_data')->nullable(); // Data tambahan untuk request
            
            // Approval info
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            
            // Status
            $table->enum('status', ['pending', 'reviewed', 'approved', 'rejected', 'cancelled'])->default('pending');
            
            // Priority
            $table->string('priority')->default('normal'); // 'low', 'normal', 'high', 'urgent'
            
            // Timestamps
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamps();
            
            // Indexes
            $table->index(['approvable_type', 'approvable_id']);
            $table->index(['approval_type', 'status']);
            $table->index(['requested_by', 'status']);
            $table->index(['status', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_approvals');
    }
};
