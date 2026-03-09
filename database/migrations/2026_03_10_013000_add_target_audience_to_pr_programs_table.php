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
            if (!Schema::hasColumn('pr_programs', 'target_audience')) {
                $table->string('target_audience')->nullable()->after('broadcast_channel');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pr_programs', function (Blueprint $table) {
            if (Schema::hasColumn('pr_programs', 'target_audience')) {
                $table->dropColumn('target_audience');
            }
        });
    }
};
