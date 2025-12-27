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
        Schema::table('shooting_run_sheets', function (Blueprint $table) {
            // Make submission_id nullable to support episodes without direct music_submissions link
            $table->unsignedBigInteger('submission_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shooting_run_sheets', function (Blueprint $table) {
            // Revert submission_id to not nullable (if needed)
            $table->unsignedBigInteger('submission_id')->nullable(false)->change();
        });
    }
};
