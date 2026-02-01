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
        Schema::table('program_schedule_options', function (Blueprint $table) {
            // Explicitly modify to TEXT to ensure it can hold longer strings
            $table->text('review_notes')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down needed as we just want to ensure it's TEXT
    }
};
