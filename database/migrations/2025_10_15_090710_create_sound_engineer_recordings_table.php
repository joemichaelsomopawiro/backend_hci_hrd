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
        if (!Schema::hasTable('sound_engineer_recordings')) {
            Schema::create('sound_engineer_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('music_submissions')->onDelete('cascade');
            $table->date('recording_date');
            $table->string('recording_location', 255);
            $table->json('equipment_list');
            $table->json('audio_files')->nullable();
            $table->text('recording_notes')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed'])->default('scheduled');
            $table->text('completion_notes')->nullable();
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
        Schema::dropIfExists('sound_engineer_recordings');
    }
};
