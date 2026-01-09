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
        Schema::table('sound_engineer_editing', function (Blueprint $table) {
            $table->foreignId('sound_engineer_recording_id')->nullable()->after('episode_id')->constrained('sound_engineer_recordings')->onDelete('set null');
            $table->index('sound_engineer_recording_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sound_engineer_editing', function (Blueprint $table) {
            $table->dropForeign(['sound_engineer_recording_id']);
            $table->dropColumn('sound_engineer_recording_id');
        });
    }
};
