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
        // Ambil semua episode kategori musik
        $musicEpisodes = Episode::whereHas('program', function($q) {
            $q->where('category', 'musik');
        })->get();

        foreach ($musicEpisodes as $episode) {
            // Hard Reset: Re-generate agar role-role baru (Setting/Shooting/Prod Creative) ikut muncul
            $episode->deadlines()->delete();
            $episode->generateDeadlines();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
