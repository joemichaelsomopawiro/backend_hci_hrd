<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_point_settings', function (Blueprint $table) {
            $table->id();
            $table->string('role', 100); // e.g. 'producer', 'kreatif', 'editor'
            $table->string('program_type', 50)->default('regular'); // 'regular' or 'musik'
            $table->integer('points_on_time')->default(5);
            $table->integer('points_late')->default(2);
            $table->integer('points_not_done')->default(-5);
            $table->integer('quality_min')->default(1);
            $table->integer('quality_max')->default(5);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['role', 'program_type']);
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('kpi_quality_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('pr_episode_id')->nullable(); // For program regular
            $table->unsignedBigInteger('music_episode_id')->nullable(); // For program musik
            $table->string('program_type', 50)->default('regular');
            $table->string('workflow_step', 100)->nullable();
            $table->integer('quality_score')->default(3); // 1-5
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('scored_by');
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('scored_by')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['employee_id', 'program_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_quality_scores');
        Schema::dropIfExists('kpi_point_settings');
    }
};
