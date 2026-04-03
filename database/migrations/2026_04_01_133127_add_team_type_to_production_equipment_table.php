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
        Schema::table('production_equipment', function (Blueprint $table) {
            $table->string('team_type')->nullable()->after('request_group_id')->comment('setting, shooting, vocal_recording, creative');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_equipment', function (Blueprint $table) {
            $table->dropColumn('team_type');
        });
    }
};
