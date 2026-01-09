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
        // Check if table exists
        if (!Schema::hasTable('songs')) {
            return;
        }
        
        Schema::table('songs', function (Blueprint $table) {
            // Add columns only if they don't exist
            if (!Schema::hasColumn('songs', 'audio_file_path')) {
                $table->string('audio_file_path')->nullable()->after('notes')->comment('Path to audio file');
            }
            if (!Schema::hasColumn('songs', 'audio_file_name')) {
                $table->string('audio_file_name')->nullable()->after('audio_file_path')->comment('Original audio file name');
            }
            if (!Schema::hasColumn('songs', 'file_size')) {
                $table->integer('file_size')->nullable()->after('audio_file_name')->comment('File size in bytes');
            }
            if (!Schema::hasColumn('songs', 'mime_type')) {
                $table->string('mime_type')->nullable()->after('file_size')->comment('MIME type of audio file');
            }
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