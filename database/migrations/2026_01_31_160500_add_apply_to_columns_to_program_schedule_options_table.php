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
            $table->string('apply_to')->default('all')->after('platform'); // 'all' or 'select'
            $table->json('target_episode_ids')->nullable()->after('apply_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_schedule_options', function (Blueprint $table) {
            $table->dropColumn(['apply_to', 'target_episode_ids']);
        });
    }
};
