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
        Schema::table('songs', function (Blueprint $table) {
            $table->string('audio_file_path')->nullable()->after('notes')->comment('Path to audio file');
            $table->string('audio_file_name')->nullable()->after('audio_file_path')->comment('Original audio file name');
            $table->integer('file_size')->nullable()->after('audio_file_name')->comment('File size in bytes');
            $table->string('mime_type')->nullable()->after('file_size')->comment('MIME type of audio file');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->dropColumn(['audio_file_path', 'audio_file_name', 'file_size', 'mime_type']);
        });
    }
};