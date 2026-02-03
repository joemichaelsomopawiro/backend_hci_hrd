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
        Schema::table('music_arrangements', function (Blueprint $table) {
            $table->string('sound_engineer_help_file_link', 2048)->nullable()->after('sound_engineer_help_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_arrangements', function (Blueprint $table) {
            $table->dropColumn('sound_engineer_help_file_link');
        });
    }
};
