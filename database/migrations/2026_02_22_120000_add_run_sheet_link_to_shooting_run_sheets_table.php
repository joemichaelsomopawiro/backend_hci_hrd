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
        Schema::table('shooting_run_sheets', function (Blueprint $table) {
            if (!Schema::hasColumn('shooting_run_sheets', 'run_sheet_link')) {
                $table->string('run_sheet_link', 255)->nullable()->after('shooting_notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shooting_run_sheets', function (Blueprint $table) {
            if (Schema::hasColumn('shooting_run_sheets', 'run_sheet_link')) {
                $table->dropColumn('run_sheet_link');
            }
        });
    }
};
