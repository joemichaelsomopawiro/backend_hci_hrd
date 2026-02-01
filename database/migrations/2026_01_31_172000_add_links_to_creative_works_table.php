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
        Schema::table('creative_works', function (Blueprint $table) {
            $table->string('script_link')->nullable()->after('script_content');
            $table->string('storyboard_link')->nullable()->after('storyboard_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('creative_works', function (Blueprint $table) {
            $table->dropColumn(['script_link', 'storyboard_link']);
        });
    }
};
