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
        if (Schema::hasColumn('programs', 'target_audience')) {
            return;
        }
        Schema::table('programs', function (Blueprint $table) {
            $table->string('target_audience')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('programs', 'target_audience')) {
            return;
        }
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('target_audience');
        });
    }
};
