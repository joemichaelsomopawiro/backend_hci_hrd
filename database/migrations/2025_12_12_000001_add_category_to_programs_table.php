<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Menambahkan field category untuk kategori program (musik, live_tv, dll)
     */
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            if (!Schema::hasColumn('programs', 'category')) {
                $table->enum('category', [
                    'musik',           // Program lagu musik
                    'live_tv',         // Program live TV
                    'regular',         // Program regular
                    'special',         // Program khusus
                    'other'            // Lainnya
                ])->default('regular')->after('description');
                
                $table->index('category');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            if (Schema::hasColumn('programs', 'category')) {
                $table->dropIndex(['category']);
                $table->dropColumn('category');
            }
        });
    }
};

