<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Program Approvals - Sistem approval untuk semua bidang dalam program management
     * Approval needed for:
     * - Jadwal tayang
     * - Rundown episode
     * - Perubahan jadwal syuting
     * - Cancel jadwal syuting
     * - Proposal program
     */
    public function up(): void
    {
        Schema::create('program_approvals', function (Blueprint $table) {
            $table->id();
            
            // Polymorphic relationship - bisa untuk berbagai tipe approval
            $table->morphs('approvable'); // approvable_id, approvable_type
            
            // Jenis Approval
            $table->enum('approval_type', [
                'program_proposal',        // Approval proposal program
                'program_schedule',        // Approval jadwal tayang program
                'episode_rundown',         // Approval rundown episode
                'production_schedule',     // Approval jadwal syuting
                'schedule_change',         // Approval perubahan jadwal syuting
                'schedule_cancellation',   // Approval pembatalan jadwal syuting
                'deadline_extension'       // Approval perpanjangan deadline
            ]);
            
            // Request Info
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('requested_at')->default(now());
            $table->text('request_notes')->nullable();
            $table->json('request_data')->nullable(); // Data perubahan yang diminta
            
            // Current Data (untuk schedule_change)
            $table->json('current_data')->nullable(); // Data saat ini sebelum perubahan
            
            // Approval Status
            $table->enum('status', [
                'pending',           // Menunggu review
                'reviewed',          // Sudah direview, menunggu keputusan
                'approved',          // Disetujui
                'rejected',          // Ditolak
                'cancelled',         // Dibatalkan oleh requester
                'auto_approved'      // Auto-approved (untuk kondisi khusus)
            ])->default('pending');
            
            // Reviewer Info
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            
            // Approver Info (Manager Broadcasting atau Manager Program)
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            
            // Rejection Info
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_notes')->nullable();
            
            // Priority
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            
            // Due Date (jika urgent, butuh approval cepat)
            $table->dateTime('due_date')->nullable();
            
            $table->timestamps();
            
            $table->index(['approvable_type', 'approvable_id', 'status']);
            $table->index(['approval_type', 'status']);
            $table->index(['requested_by', 'status']);
            $table->index(['approved_by', 'status']);
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

