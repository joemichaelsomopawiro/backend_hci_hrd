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
            // Add file_link field for external storage URLs
            // This replaces the need for file_path, file_name, file_size, mime_type
            // but we keep those fields for backward compatibility (nullable)
            $table->text('file_link')->nullable()->after('mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sound_engineer_recordings', function (Blueprint $table) {
            $table->dropColumn('file_link');
        });
    }
};
