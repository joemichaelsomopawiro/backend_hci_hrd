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
        Schema::table('produksi_works', function (Blueprint $table) {
            $table->foreignId('run_sheet_id')->nullable()->after('creative_work_id')->constrained('shooting_run_sheets')->onDelete('set null');
            $table->json('shooting_files')->nullable()->after('needs_requests'); // File hasil syuting
            $table->text('shooting_file_links')->nullable()->after('shooting_files'); // Link file hasil syuting
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produksi_works', function (Blueprint $table) {
            $table->dropForeign(['run_sheet_id']);
            $table->dropColumn(['run_sheet_id', 'shooting_files', 'shooting_file_links']);
        });
    }
};

