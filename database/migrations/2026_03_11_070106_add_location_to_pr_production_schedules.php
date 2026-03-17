<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pr_production_schedules', function (Blueprint $table) {
            $table->string('scheduled_location')->nullable()->after('schedule_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pr_production_schedules', function (Blueprint $table) {
            $table->dropColumn('scheduled_location');
        });
    }
};
