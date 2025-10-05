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
        Schema::create('music_workflow_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('notification_type', [
                'submission_received', 'arrangement_request', 'arrangement_approved',
                'arrangement_rejected', 'sound_engineering_request', 'quality_control_request',
                'creative_work_request', 'final_approval_request', 'workflow_completed',
                'workflow_rejected'
            ]);
            $table->string('title');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('submission_id')->references('id')->on('music_submissions')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes
            $table->index(['user_id', 'is_read']);
            $table->index(['submission_id', 'notification_type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_workflow_notifications');
    }
};






