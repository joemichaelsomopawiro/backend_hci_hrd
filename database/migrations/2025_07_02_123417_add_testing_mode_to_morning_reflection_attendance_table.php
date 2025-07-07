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
        Schema::table('morning_reflection_attendance', function (Blueprint $table) {
            if (!Schema::hasColumn('morning_reflection_attendance', 'testing_mode')) {
                $table->boolean('testing_mode')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('morning_reflection_attendance', function (Blueprint $table) {
            if (Schema::hasColumn('morning_reflection_attendance', 'testing_mode')) {
                $table->dropColumn('testing_mode');
            }
        });
    }
};
