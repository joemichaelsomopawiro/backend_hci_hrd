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
        Schema::table('episodes', function (Blueprint $table) {
            $table->enum('status', [
                'draft', 
                'rundown_pending_approval', 
                'rundown_rejected',
                'approved_for_production',
                'in_production', 
                'script_overdue',
                'production_overdue',
                'ready_for_review',
                'reviewed',
                'aired',
                'cancelled'
            ])->default('draft')->change();
            
            $table->timestamp('production_deadline')->nullable();
            $table->timestamp('script_deadline')->nullable();
            $table->timestamp('production_started_at')->nullable();
            $table->timestamp('production_completed_at')->nullable();
            $table->text('submission_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->text('approval_notes')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_notes')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('rejected_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['rejected_by']);
            
            $table->dropColumn([
                'production_deadline',
                'script_deadline',
                'production_started_at',
                'production_completed_at',
                'submission_notes',
                'submitted_at',
                'submitted_by',
                'approval_notes',
                'approved_by',
                'approved_at',
                'rejection_notes',
                'rejected_by',
                'rejected_at'
            ]);
            
            $table->enum('status', ['draft', 'in_production', 'ready_for_review', 'reviewed', 'aired', 'cancelled'])->default('draft')->change();
        });
    }
};
