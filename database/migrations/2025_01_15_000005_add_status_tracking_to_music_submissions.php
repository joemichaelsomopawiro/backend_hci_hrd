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
        Schema::table('music_submissions', function (Blueprint $table) {
            // Add status column for tracking submission status
            $table->enum('submission_status', [
                'draft',           // Belum dikirim ke Producer
                'pending',         // Sudah dikirim, menunggu review
                'under_review',    // Sedang direview Producer
                'approved',        // Sudah disetujui, dalam proses
                'rejected',        // Ditolak, bisa diperbaiki
                'completed'        // Selesai, hanya bisa lihat
            ])->default('draft')->after('current_state');
            
            // Add tracking columns
            $table->text('producer_feedback')->nullable()->after('producer_notes');
            $table->timestamp('submitted_at')->nullable()->after('producer_feedback');
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->timestamp('completed_at')->nullable()->after('rejected_at');
            
            // Add version tracking for resubmissions
            $table->integer('version')->default(1)->after('completed_at');
            $table->bigInteger('parent_submission_id')->nullable()->after('version');
            
            // Add indexes for better performance
            $table->index(['music_arranger_id', 'submission_status']);
            $table->index(['submission_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_submissions', function (Blueprint $table) {
            $table->dropIndex(['music_arranger_id', 'submission_status']);
            $table->dropIndex(['submission_status', 'created_at']);
            
            $table->dropColumn([
                'submission_status',
                'producer_feedback',
                'submitted_at',
                'approved_at',
                'rejected_at',
                'completed_at',
                'version',
                'parent_submission_id'
            ]);
        });
    }
};






