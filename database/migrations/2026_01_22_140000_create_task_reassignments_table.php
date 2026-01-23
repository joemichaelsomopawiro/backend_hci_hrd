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
        Schema::create('task_reassignments', function (Blueprint $table) {
            $table->id();
            $table->string('task_type'); // 'editor_work', 'creative_work', etc
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('program_id')->nullable(); // For filtering
            $table->unsignedBigInteger('original_user_id');
            $table->unsignedBigInteger('new_user_id');
            $table->unsignedBigInteger('reassigned_by_user_id');
            $table->text('reason')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['task_type', 'task_id'], 'idx_task_type_id');
            $table->index('new_user_id', 'idx_new_user');
            $table->index('original_user_id', 'idx_original_user');
            $table->index('reassigned_by_user_id', 'idx_reassigned_by');
            $table->index('program_id', 'idx_program');
            $table->index('created_at', 'idx_created_at');
            
            // Foreign keys
            $table->foreign('original_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('new_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reassigned_by_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_reassignments');
    }
};
