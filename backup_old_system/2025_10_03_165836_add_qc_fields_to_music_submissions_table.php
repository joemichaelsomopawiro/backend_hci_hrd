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
            // QC Music fields
            $table->string('qc_decision')->nullable(); // 'approved', 'needs_improvement'
            $table->integer('quality_score')->nullable(); // 1-10
            $table->json('improvement_areas')->nullable(); // Array of improvement areas
            $table->timestamp('qc_completed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'qc_decision',
                'quality_score',
                'improvement_areas',
                'qc_completed_at'
            ]);
        });
    }
};