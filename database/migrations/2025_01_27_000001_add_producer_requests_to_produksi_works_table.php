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
            $table->json('producer_requests')->nullable()->after('shooting_file_links'); // Track Producer requests (reshoot, complete files, fix)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produksi_works', function (Blueprint $table) {
            $table->dropColumn('producer_requests');
        });
    }
};
