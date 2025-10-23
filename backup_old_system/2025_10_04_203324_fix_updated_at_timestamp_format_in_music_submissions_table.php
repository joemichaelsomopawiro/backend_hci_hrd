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
            // Fix updated_at column to ensure proper timestamp format
            $table->timestamp('updated_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('music_submissions', function (Blueprint $table) {
            // Revert the change if needed
            $table->timestamp('updated_at')->nullable()->change();
        });
    }
};