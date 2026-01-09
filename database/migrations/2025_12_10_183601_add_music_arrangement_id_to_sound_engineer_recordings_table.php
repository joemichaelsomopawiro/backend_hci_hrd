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
        Schema::table('sound_engineer_recordings', function (Blueprint $table) {
            $table->foreignId('music_arrangement_id')->nullable()->after('episode_id')->constrained('music_arrangements')->onDelete('set null');
            $table->index('music_arrangement_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sound_engineer_recordings', function (Blueprint $table) {
            $table->dropForeign(['music_arrangement_id']);
            $table->dropColumn('music_arrangement_id');
        });
    }
};
