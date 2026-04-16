<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Episode;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ambil semua episode kategori musik menggunakan kolom 'category' yang sudah dikonfirmasi benar
        $musicEpisodes = Episode::whereHas('program', function($q) {
            $q->where('category', 'musik');
        })->get();

        foreach ($musicEpisodes as $episode) {
            // 2. Hard Reset: Hapus SEMUA deadline lama agar tidak ada "Ghost Roles" di DB
            $episode->deadlines()->delete();

            // 3. Generate ulang dengan logika baru (H-15, H-11 rill)
            $episode->generateDeadlines();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed for data cleanup
    }
};
