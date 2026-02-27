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
        Schema::table('pr_programs', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->date('start_date')->nullable()->change();
            $table->time('air_time')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pr_programs', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->date('start_date')->nullable(false)->change();
            $table->time('air_time')->nullable(false)->change();
        });
    }
};
