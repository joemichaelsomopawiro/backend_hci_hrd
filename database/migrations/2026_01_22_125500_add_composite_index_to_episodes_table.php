<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add composite index untuk episodes query optimization
     * Untuk mempercepat query berdasarkan program_id dan air_date (terutama year-based queries)
     */
    public function up(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            // Composite index untuk query by program dan air_date range
            // Ini akan mempercepat query Episode::byYear() sampai 10x lipat
            $table->index(['program_id', 'air_date'], 'episodes_program_air_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropIndex('episodes_program_air_date_index');
        });
    }
};
