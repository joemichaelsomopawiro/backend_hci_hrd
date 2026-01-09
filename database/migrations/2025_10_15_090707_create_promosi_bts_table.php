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
        // Check if table already exists
        if (!Schema::hasTable('promosi_bts')) {
            Schema::create('promosi_bts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('music_submissions')->onDelete('cascade');
            $table->string('bts_video_path', 500)->nullable();
            $table->string('bts_video_url', 500)->nullable();
            $table->json('talent_photos')->nullable();
            $table->foreignId('shooting_schedule_id')->nullable()->constrained('shooting_run_sheets')->onDelete('set null');
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promosi_bts');
    }
};
