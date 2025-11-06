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
        Schema::create('sound_engineer_editing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
            $table->foreignId('sound_engineer_id')->constrained('users')->onDelete('cascade');
            $table->string('vocal_file_path')->nullable();
            $table->string('final_file_path')->nullable();
            $table->text('editing_notes')->nullable();
            $table->text('submission_notes')->nullable();
            $table->enum('status', ['in_progress', 'submitted', 'approved', 'rejected', 'revision_needed'])->default('in_progress');
            $table->date('estimated_completion')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sound_engineer_editing');
    }
};











