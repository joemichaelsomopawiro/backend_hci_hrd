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
        Schema::create('shooting_run_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('music_submissions')->onDelete('cascade');
            $table->date('shooting_date');
            $table->string('location', 255);
            $table->json('crew_list');
            $table->json('equipment_list');
            $table->text('shooting_notes')->nullable();
            $table->enum('status', ['planned', 'in_progress', 'completed'])->default('planned');
            $table->json('uploaded_files')->nullable(); // Files after shooting
            $table->text('completion_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shooting_run_sheets');
    }
};
