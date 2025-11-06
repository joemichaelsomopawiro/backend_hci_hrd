<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add foreign key constraints after all tables are created
     */
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->foreign('production_team_id')->references('id')->on('production_teams')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropForeign(['production_team_id']);
        });
    }
};














