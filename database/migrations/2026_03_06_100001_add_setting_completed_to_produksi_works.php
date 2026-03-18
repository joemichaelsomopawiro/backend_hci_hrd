<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tim Setting selesai terpisah dari Tim Syuting: setting_completed_at hanya menandai bagian Tim Setting selesai,
     * status work tetap in_progress sampai Tim Syuting selesai (run sheet, link file, kembalikan alat, lalu complete).
     */
    public function up(): void
    {
        if (!Schema::hasTable('produksi_works')) {
            return;
        }

        Schema::table('produksi_works', function (Blueprint $table) {
            if (!Schema::hasColumn('produksi_works', 'setting_completed_at')) {
                $table->timestamp('setting_completed_at')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('produksi_works', 'setting_completed_by')) {
                $table->foreignId('setting_completed_by')->nullable()->after('setting_completed_at')->constrained('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produksi_works', function (Blueprint $table) {
            if (Schema::hasColumn('produksi_works', 'setting_completed_by')) {
                $table->dropForeign(['setting_completed_by']);
                $table->dropColumn('setting_completed_by');
            }
            if (Schema::hasColumn('produksi_works', 'setting_completed_at')) {
                $table->dropColumn('setting_completed_at');
            }
        });
    }
};
