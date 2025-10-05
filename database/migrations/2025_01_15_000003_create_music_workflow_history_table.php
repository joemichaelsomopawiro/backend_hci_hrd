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
        Schema::create('music_workflow_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('submission_id');
            $table->string('from_state', 50)->nullable();
            $table->string('to_state', 50);
            $table->unsignedBigInteger('action_by_user_id');
            $table->text('action_notes')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('submission_id')->references('id')->on('music_submissions')->onDelete('cascade');
            $table->foreign('action_by_user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes
            $table->index('submission_id');
            $table->index('action_by_user_id');
            $table->index(['submission_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_workflow_history');
    }
};






