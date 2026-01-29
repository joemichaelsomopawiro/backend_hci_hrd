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
        Schema::create('program_schedule_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->onDelete('cascade');
            $table->foreignId('episode_id')->nullable()->constrained('episodes')->onDelete('cascade');
            $table->foreignId('submitted_by')->constrained('users')->onDelete('cascade');
            $table->json('schedule_options'); // Array of schedule options with date, time, notes
            $table->string('platform')->default('all'); // 'tv', 'youtube', 'website', 'all'
            $table->string('status')->default('pending'); // 'pending', 'approved', 'revised', 'rejected'
            $table->text('submission_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->integer('selected_option_index')->nullable(); // Index of approved option (0-based)
            $table->json('approved_schedule')->nullable(); // Final approved schedule details
            $table->timestamps();

            // Indexes for performance
            $table->index('program_id');
            $table->index('episode_id');
            $table->index('status');
            $table->index('submitted_by');
            $table->index('reviewed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_schedule_options');
    }
};
