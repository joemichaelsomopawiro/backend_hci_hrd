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
        Schema::table('pr_design_grafis_works', function (Blueprint $table) {
            $table->string('episode_poster_link')->nullable()->after('bts_thumbnail_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pr_design_grafis_works', function (Blueprint $table) {
            $table->dropColumn('episode_poster_link');
        });
    }
};
