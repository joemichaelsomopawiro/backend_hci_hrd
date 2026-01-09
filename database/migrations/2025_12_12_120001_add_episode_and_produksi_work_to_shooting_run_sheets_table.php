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
            $table->foreignId('episode_id')->nullable()->after('submission_id')->constrained('episodes')->onDelete('cascade');
            $table->foreignId('produksi_work_id')->nullable()->after('episode_id')->constrained('produksi_works')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shooting_run_sheets', function (Blueprint $table) {
            $table->dropForeign(['episode_id']);
            $table->dropForeign(['produksi_work_id']);
            $table->dropColumn(['episode_id', 'produksi_work_id']);
        });
    }
};

