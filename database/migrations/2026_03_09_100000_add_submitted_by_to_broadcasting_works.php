<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * submitted_by = user who submitted the editor result for QC (Editor). Used for "Dikirim oleh" in Distribution Manager.
     */
    public function up(): void
    {
        Schema::table('broadcasting_works', function (Blueprint $table) {
            $table->foreignId('submitted_by')->nullable()->after('created_by')->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('broadcasting_works', function (Blueprint $table) {
            $table->dropForeign(['submitted_by']);
        });
    }
};
