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
            // Add missing fields that are referenced in the controller
            if (!Schema::hasColumn('music_submissions', 'current_state')) {
                $table->string('current_state')->nullable()->after('id');
            }
            
            if (!Schema::hasColumn('music_submissions', 'submission_status')) {
                $table->string('submission_status')->nullable()->after('current_state');
            }
            
            if (!Schema::hasColumn('music_submissions', 'status')) {
                $table->string('status')->nullable()->after('submission_status');
            }
            
            if (!Schema::hasColumn('music_submissions', 'song_id')) {
                $table->unsignedBigInteger('song_id')->nullable()->after('status');
            }
            
            if (!Schema::hasColumn('music_submissions', 'proposed_singer_id')) {
                $table->unsignedBigInteger('proposed_singer_id')->nullable()->after('song_id');
            }
            
            if (!Schema::hasColumn('music_submissions', 'arrangement_notes')) {
                $table->text('arrangement_notes')->nullable()->after('proposed_singer_id');
            }
            
            if (!Schema::hasColumn('music_submissions', 'requested_date')) {
                $table->date('requested_date')->nullable()->after('arrangement_notes');
            }
            
            if (!Schema::hasColumn('music_submissions', 'producer_notes')) {
                $table->text('producer_notes')->nullable()->after('requested_date');
            }
            
            if (!Schema::hasColumn('music_submissions', 'approved_singer_id')) {
                $table->unsignedBigInteger('approved_singer_id')->nullable()->after('producer_notes');
            }
            
            if (!Schema::hasColumn('music_submissions', 'arrangement_file_path')) {
                $table->string('arrangement_file_path')->nullable()->after('approved_singer_id');
            }
            
            if (!Schema::hasColumn('music_submissions', 'arrangement_file_url')) {
                $table->string('arrangement_file_url')->nullable()->after('arrangement_file_path');
            }
            
            if (!Schema::hasColumn('music_submissions', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('arrangement_file_url');
            }
            
            if (!Schema::hasColumn('music_submissions', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('submitted_at');
            }
            
            if (!Schema::hasColumn('music_submissions', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_at');
            }
            
            if (!Schema::hasColumn('music_submissions', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('rejected_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'current_state',
                'submission_status', 
                'status',
                'song_id',
                'proposed_singer_id',
                'arrangement_notes',
                'requested_date',
                'producer_notes',
                'approved_singer_id',
                'arrangement_file_path',
                'arrangement_file_url',
                'submitted_at',
                'approved_at',
                'rejected_at',
                'completed_at'
            ]);
        });
    }
};
