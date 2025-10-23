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
            // Fix processed_at field to allow NULL values and proper timestamp format
            $table->timestamp('processed_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_submissions', function (Blueprint $table) {
            // Revert processed_at field changes
            $table->timestamp('processed_at')->nullable(false)->change();
        });
    }
};
