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
        Schema::table('pr_calendar_events', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('reminder_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pr_calendar_events', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
