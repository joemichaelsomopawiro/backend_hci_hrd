<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambahkan kolom producer acceptance ke tabel programs.
     * Producer harus accept program sebelum workflow (Music Arranger, dll) bisa berjalan.
     */
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->boolean('producer_accepted')->default(false)->after('status');
            $table->unsignedBigInteger('producer_accepted_by')->nullable()->after('producer_accepted');
            $table->timestamp('producer_accepted_at')->nullable()->after('producer_accepted_by');
            $table->timestamp('producer_rejected_at')->nullable()->after('producer_accepted_at');
            $table->text('producer_rejection_notes')->nullable()->after('producer_rejected_at');

            $table->foreign('producer_accepted_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropForeign(['producer_accepted_by']);
            $table->dropColumn([
                'producer_accepted',
                'producer_accepted_by',
                'producer_accepted_at',
                'producer_rejected_at',
                'producer_rejection_notes',
            ]);
        });
    }
};
