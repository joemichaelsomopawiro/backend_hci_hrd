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
            // Sound Engineer fields
            $table->unsignedBigInteger('assigned_sound_engineer_id')->nullable();
            $table->timestamp('sound_engineering_started_at')->nullable();
            $table->timestamp('sound_engineering_completed_at')->nullable();
            $table->timestamp('sound_engineering_rejected_at')->nullable();
            $table->text('sound_engineer_feedback')->nullable();
            
            // Creative fields
            $table->unsignedBigInteger('assigned_creative_id')->nullable();
            $table->timestamp('creative_work_started_at')->nullable();
            $table->timestamp('creative_work_completed_at')->nullable();
            
            // Foreign key constraints
            $table->foreign('assigned_sound_engineer_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('assigned_creative_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_submissions', function (Blueprint $table) {
            $table->dropForeign(['assigned_sound_engineer_id']);
            $table->dropForeign(['assigned_creative_id']);
            
            $table->dropColumn([
                'assigned_sound_engineer_id',
                'sound_engineering_started_at',
                'sound_engineering_completed_at',
                'sound_engineering_rejected_at',
                'sound_engineer_feedback',
                'assigned_creative_id',
                'creative_work_started_at',
                'creative_work_completed_at'
            ]);
        });
    }
};