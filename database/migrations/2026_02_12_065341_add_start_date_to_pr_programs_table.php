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
        if (!Schema::hasColumn('pr_programs', 'start_date')) {
            Schema::table('pr_programs', function (Blueprint $table) {
                $table->date('start_date')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pr_programs', function (Blueprint $table) {
            $table->dropColumn('start_date');
        });
    }
};
