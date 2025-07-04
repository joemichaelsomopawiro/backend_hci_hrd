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
        Schema::table('morning_reflections', function (Blueprint $table) {
            $table->boolean('testing_mode')->default(false)->after('join_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('morning_reflections', function (Blueprint $table) {
            $table->dropColumn('testing_mode');
        });
    }
};
