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
        Schema::table('promotion_works', function (Blueprint $table) {
            // Add file_links field (JSON array) for external storage URLs
            // This replaces the need for file_paths (array)
            // but we keep file_paths for backward compatibility (nullable)
            $table->json('file_links')->nullable()->after('file_paths');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotion_works', function (Blueprint $table) {
            $table->dropColumn('file_links');
        });
    }
};
