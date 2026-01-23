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
            // Add file_link fields for external storage URLs
            // This replaces the need for vocal_file_path and final_file_path
            // but we keep those fields for backward compatibility (nullable)
            $table->text('vocal_file_link')->nullable()->after('vocal_file_path');
            $table->text('final_file_link')->nullable()->after('final_file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sound_engineer_editing', function (Blueprint $table) {
            $table->dropColumn(['vocal_file_link', 'final_file_link']);
        });
    }
};
