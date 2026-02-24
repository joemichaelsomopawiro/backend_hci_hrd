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
            if (!Schema::hasColumn('music_submissions', 'program_id')) {
                $table->foreignId('program_id')->nullable()->after('current_state')->constrained('programs')->onDelete('cascade');
            }
            if (!Schema::hasColumn('music_submissions', 'episode_id')) {
                $table->foreignId('episode_id')->nullable()->after('program_id')->constrained('episodes')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_submissions', function (Blueprint $table) {
            $table->dropForeign(['episode_id']);
            $table->dropColumn('episode_id');
            $table->dropForeign(['program_id']);
            $table->dropColumn('program_id');
        });
    }
};
